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
    // Parser le body avec gestion d'erreur
    let data;
    try {
      data = JSON.parse(event.body);
    } catch (parseError) {
      return {
        statusCode: 400,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Données JSON invalides' })
      };
    }
    
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
    const supabaseUrl = process.env.SUPABASE_URL || 'https://srbzvjrqbhtuyzlwdghn.supabase.co';
    const supabaseKey = process.env.SUPABASE_ANON_KEY || 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM';
    
    if (!supabaseUrl || !supabaseKey) {
      console.error('Configuration Supabase manquante:', { hasUrl: !!supabaseUrl, hasKey: !!supabaseKey });
      return {
        statusCode: 500,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Configuration Supabase manquante. Vérifiez les variables d\'environnement.' })
      };
    }

    const supabase = createClient(supabaseUrl, supabaseKey);

    // Récupérer l'utilisateur par téléphone
    const { data: users, error: fetchError } = await supabase
      .from('utilisateurs')
      .select('*')
      .eq('telephone', data.telephone.trim())
      .limit(1);

    if (fetchError) {
      console.error('Erreur Supabase fetch:', fetchError);
      return {
        statusCode: 500,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ 
          success: false, 
          message: 'Erreur de connexion à la base de données',
          error: process.env.NODE_ENV === 'development' ? fetchError.message : undefined
        })
      };
    }

    if (!users || users.length === 0) {
      // Ne pas révéler si l'utilisateur existe ou non (sécurité)
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
    let passwordMatch = false;
    try {
      // Vérifier si le mot de passe est hashé avec bcrypt
      if (user.mot_de_passe && user.mot_de_passe.startsWith('$2')) {
        passwordMatch = await bcrypt.compare(data.mot_de_passe, user.mot_de_passe);
      } else {
        // Fallback pour les mots de passe non hashés (développement uniquement)
        console.warn('Mot de passe non hashé détecté pour utilisateur:', user.id);
        passwordMatch = data.mot_de_passe === user.mot_de_passe;
      }
    } catch (bcryptError) {
      console.error('Erreur bcrypt:', bcryptError);
      return {
        statusCode: 500,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Erreur lors de la vérification du mot de passe' })
      };
    }
    
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
    console.error('Stack:', error.stack);
    return {
      statusCode: 500,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*'
      },
      body: JSON.stringify({
        success: false,
        message: 'Erreur serveur lors de la connexion',
        error: process.env.NODE_ENV === 'development' ? error.message : undefined
      })
    };
  }
};

