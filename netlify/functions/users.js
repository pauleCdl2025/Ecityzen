// Netlify Function pour la gestion des utilisateurs
const { createClient } = require('@supabase/supabase-js');

exports.handler = async (event, context) => {
  // Gérer les requêtes OPTIONS (CORS preflight)
  if (event.httpMethod === 'OPTIONS') {
    return {
      statusCode: 200,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, PUT, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type'
      },
      body: ''
    };
  }

  // Initialiser Supabase
  const supabaseUrl = process.env.SUPABASE_URL;
  const supabaseKey = process.env.SUPABASE_ANON_KEY;
  
  if (!supabaseUrl || !supabaseKey) {
    return {
      statusCode: 500,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*'
      },
      body: JSON.stringify({ success: false, message: 'Configuration Supabase manquante' })
    };
  }

  const supabase = createClient(supabaseUrl, supabaseKey);

  // Extraire user_id et user_role depuis le body ou les query params
  let userId = null;
  let userRole = null;
  
  // Essayer d'abord les query params (pour GET)
  if (event.queryStringParameters) {
    userId = event.queryStringParameters._user_id || null;
    userRole = event.queryStringParameters._user_role || null;
  }
  
  // Sinon, essayer le body (pour PUT/POST)
  if (!userId && event.body) {
    try {
      const body = JSON.parse(event.body);
      userId = body._user_id || null;
      userRole = body._user_role || null;
    } catch (e) {
      // Ignorer si pas de body ou erreur de parsing
    }
  }

  // GET: Récupérer la liste des utilisateurs (managers/superadmins seulement)
  if (event.httpMethod === 'GET') {
    if (!userId || !['manager', 'superadmin'].includes(userRole)) {
      return {
        statusCode: 403,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Accès non autorisé' })
      };
    }

    try {
      const { data: users, error } = await supabase
        .from('utilisateurs')
        .select('*')
        .order('date_creation', { ascending: false });

      if (error) throw error;

      // Ne jamais envoyer les mots de passe
      const sanitizedUsers = users.map(user => {
        const { mot_de_passe, ...userWithoutPassword } = user;
        return {
          ...userWithoutPassword,
          name: user.nom || user.name, // Compatibilité avec le frontend
          statut: user.statut || 'actif' // S'assurer que statut existe
        };
      });

      return {
        statusCode: 200,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: true, data: sanitizedUsers, message: 'Utilisateurs récupérés' })
      };

    } catch (error) {
      console.error('Erreur récupération utilisateurs:', error);
      return {
        statusCode: 500,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Erreur serveur' })
      };
    }
  }

  // PUT: Mettre à jour un utilisateur
  if (event.httpMethod === 'PUT') {
    if (!userId) {
      return {
        statusCode: 401,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Non authentifié' })
      };
    }

    try {
      const data = JSON.parse(event.body);
      
      if (!data.id) {
        return {
          statusCode: 400,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: false, message: 'ID utilisateur manquant' })
        };
      }

      // Vérifier que l'utilisateur existe
      const { data: existingUser, error: fetchError } = await supabase
        .from('utilisateurs')
        .select('*')
        .eq('id', data.id)
        .single();

      if (fetchError || !existingUser) {
        return {
          statusCode: 404,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: false, message: 'Utilisateur non trouvé' })
        };
      }

      // Préparer les données de mise à jour
      const updateData = {};

      if (data.nom) updateData.nom = data.nom.trim();
      if (data.telephone) {
        // Vérifier que le téléphone n'est pas déjà utilisé par un autre utilisateur
        const { data: phoneCheck, error: phoneError } = await supabase
          .from('utilisateurs')
          .select('id')
          .eq('telephone', data.telephone.trim())
          .limit(1);

        if (!phoneError && phoneCheck && phoneCheck.length > 0 && phoneCheck[0].id != data.id) {
          return {
            statusCode: 400,
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify({ success: false, message: 'Ce numéro de téléphone est déjà utilisé' })
          };
        }
        updateData.telephone = data.telephone.trim();
      }
      if (data.email !== undefined) updateData.email = data.email ? data.email.trim() : null;
      if (data.localisation !== undefined) updateData.localisation = data.localisation ? data.localisation.trim() : null;
      if (data.secteur !== undefined) updateData.secteur = data.secteur ? data.secteur.trim() : null;
      if (data.entreprise !== undefined) updateData.entreprise = data.entreprise ? data.entreprise.trim() : null;

      // Gestion du changement de mot de passe
      if (data.ancien_mot_de_passe && data.nouveau_mot_de_passe) {
        const bcrypt = require('bcryptjs');
        // Vérifier l'ancien mot de passe
        const passwordMatch = await bcrypt.compare(data.ancien_mot_de_passe, existingUser.mot_de_passe);
        if (!passwordMatch) {
          return {
            statusCode: 400,
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify({ success: false, message: 'Ancien mot de passe incorrect' })
          };
        }
        // Vérifier la longueur du nouveau mot de passe
        if (data.nouveau_mot_de_passe.length < 6) {
          return {
            statusCode: 400,
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify({ success: false, message: 'Le nouveau mot de passe doit contenir au moins 6 caractères' })
          };
        }
        updateData.mot_de_passe = await bcrypt.hash(data.nouveau_mot_de_passe, 10);
      }

      // Permettre aux managers/admins de changer le statut et le rôle
      if (['manager', 'superadmin'].includes(userRole)) {
        if (data.statut !== undefined) {
          updateData.statut = data.statut;
        }
        if (data.role !== undefined) {
          updateData.role = data.role;
        }
      }

      if (Object.keys(updateData).length === 0) {
        return {
          statusCode: 400,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: false, message: 'Aucune modification à effectuer' })
        };
      }

      // Mettre à jour l'utilisateur
      const { data: updatedUser, error: updateError } = await supabase
        .from('utilisateurs')
        .update(updateData)
        .eq('id', data.id)
        .select()
        .single();

      if (updateError) throw updateError;

      // Ne jamais envoyer le mot de passe
      const { mot_de_passe, ...userWithoutPassword } = updatedUser;
      const response = {
        ...userWithoutPassword,
        name: updatedUser.nom
      };

      return {
        statusCode: 200,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: true, data: response, message: 'Profil mis à jour avec succès' })
      };

    } catch (error) {
      console.error('Erreur mise à jour utilisateur:', error);
      return {
        statusCode: 500,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Erreur serveur' })
      };
    }
  }

  return {
    statusCode: 405,
    headers: {
      'Content-Type': 'application/json',
      'Access-Control-Allow-Origin': '*'
    },
    body: JSON.stringify({ success: false, message: 'Méthode non autorisée' })
  };
};

