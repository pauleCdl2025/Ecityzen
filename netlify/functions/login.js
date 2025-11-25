// Netlify Function pour la connexion
const { createClient } = require('@supabase/supabase-js');
const bcrypt = require('bcryptjs');

exports.handler = async (event, context) => {
  // Gérer les requêtes OPTIONS (CORS preflight)
  if (event.httpMethod === 'OPTIONS') {
    return {
      statusCode: 200,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type'
      },
      body: ''
    };
  }

  if (event.httpMethod !== 'POST') {
    return {
      statusCode: 405,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*'
      },
      body: JSON.stringify({ success: false, message: 'Méthode non autorisée' })
    };
  }

  try {
    const data = JSON.parse(event.body);
    
    // Validation
    if (!data.telephone || !data.mot_de_passe) {
      return {
        statusCode: 400,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Téléphone et mot de passe requis' })
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

    // Récupérer l'utilisateur par téléphone
    const { data: users, error: fetchError } = await supabase
      .from('utilisateurs')
      .select('*')
      .eq('telephone', data.telephone)
      .limit(1);

    if (fetchError) {
      throw fetchError;
    }

    if (!users || users.length === 0) {
      return {
        statusCode: 401,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Numéro de téléphone ou mot de passe incorrect' })
      };
    }

    const user = users[0];

    // Vérifier le statut
    if (user.statut !== 'actif') {
      let message = 'Votre compte est désactivé';
      if (user.statut === 'en_attente' && user.role === 'agent') {
        message = 'Votre demande d\'inscription est en attente de validation par un manager';
      }
      return {
        statusCode: 403,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: message })
      };
    }

    // Vérifier le mot de passe
    const passwordMatch = await bcrypt.compare(data.mot_de_passe, user.mot_de_passe);
    
    if (!passwordMatch) {
      return {
        statusCode: 401,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Numéro de téléphone ou mot de passe incorrect' })
      };
    }

    // Mettre à jour la dernière connexion
    await supabase
      .from('utilisateurs')
      .update({ derniere_connexion: new Date().toISOString() })
      .eq('id', user.id);

    // Formater la réponse
    const response = {
      success: true,
      data: {
        id: user.id,
        name: user.nom,
        telephone: user.telephone,
        email: user.email || null,
        role: user.role,
        location: user.localisation || null,
        sector: user.secteur || null,
        business: user.entreprise || null
      },
      message: 'Connexion réussie'
    };

    return {
      statusCode: 200,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
        'Set-Cookie': `session=${user.id}; Path=/; HttpOnly; SameSite=Lax`
      },
      body: JSON.stringify(response)
    };

  } catch (error) {
    console.error('Erreur login:', error);
    return {
      statusCode: 500,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*'
      },
      body: JSON.stringify({
        success: false,
        message: error.message || 'Erreur serveur'
      })
    };
  }
};

