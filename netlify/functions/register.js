// Netlify Function pour l'inscription avec hashage du mot de passe
const { createClient } = require('@supabase/supabase-js');

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
    if (!data.nom || !data.telephone || !data.mot_de_passe || !data.role) {
      return {
        statusCode: 400,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Champs manquants' })
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

    // Vérifier si le téléphone existe déjà
    const { data: existingUsers, error: checkError } = await supabase
      .from('utilisateurs')
      .select('id')
      .eq('telephone', data.telephone)
      .limit(1);

    if (checkError) {
      throw checkError;
    }

    if (existingUsers && existingUsers.length > 0) {
      return {
        statusCode: 409,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Ce numéro de téléphone est déjà utilisé' })
      };
    }

    // Hasher le mot de passe
    const bcrypt = require('bcryptjs');
    const mot_de_passe_hash = await bcrypt.hash(data.mot_de_passe, 10);

    // Générer un email factice
    const email = 'user_' + data.telephone.replace(/\D/g, '') + '@ecityzen.ga';

    // Préparer les données utilisateur
    // Les agents doivent être validés par le manager
    const statut = (data.role === 'agent') ? 'en_attente' : 'actif';
    
    const userData = {
      nom: data.nom,
      email: email,
      telephone: data.telephone,
      role: data.role,
      mot_de_passe: mot_de_passe_hash,
      statut: statut
    };

    if (data.localisation) userData.localisation = data.localisation;
    if (data.latitude) userData.latitude = parseFloat(data.latitude);
    if (data.longitude) userData.longitude = parseFloat(data.longitude);
    if (data.entreprise) userData.entreprise = data.entreprise;
    if (data.secteur) userData.secteur = data.secteur;

    // Insérer l'utilisateur
    const { data: newUser, error: insertError } = await supabase
      .from('utilisateurs')
      .insert(userData)
      .select()
      .single();

    if (insertError) {
      throw insertError;
    }

    // Message différent selon le statut
    const message = (data.role === 'agent' && statut === 'en_attente')
      ? 'Votre demande d\'inscription a été soumise. Elle sera validée par un manager sous peu.'
      : 'Compte créé avec succès';
    
    // Formater la réponse
    const response = {
      success: true,
      data: {
        id: newUser.id,
        name: newUser.nom,
        telephone: newUser.telephone,
        email: newUser.email,
        role: newUser.role,
        location: newUser.localisation || null,
        sector: newUser.secteur || null,
        business: newUser.entreprise || null,
        statut: newUser.statut
      },
      message: message
    };

    return {
      statusCode: 200,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*'
      },
      body: JSON.stringify(response)
    };

  } catch (error) {
    console.error('Erreur inscription:', error);
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

