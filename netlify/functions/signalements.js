// Netlify Function pour les signalements
const { createClient } = require('@supabase/supabase-js');

// Fonction helper pour enrichir avec les noms d'utilisateurs
async function enrichWithUserNames(supabase, items, userField = 'utilisateur_id', agentField = 'agent_assigné_id') {
  const userIds = new Set();
  items.forEach(item => {
    if (item[userField]) userIds.add(item[userField]);
    if (item[agentField]) userIds.add(item[agentField]);
  });
  
  if (userIds.size === 0) return items;
  
  const { data: users, error } = await supabase
    .from('utilisateurs')
    .select('id, nom')
    .in('id', Array.from(userIds));
  
  if (error) {
    console.error('Erreur récupération utilisateurs:', error);
    return items;
  }
  
  const userMap = {};
  if (users) {
    users.forEach(user => {
      userMap[user.id] = user.nom;
    });
  }
  
  return items.map(item => {
    if (item[userField] && userMap[item[userField]]) {
      item['utilisateur_nom'] = userMap[item[userField]];
    }
    if (item[agentField] && userMap[item[agentField]]) {
      item['agent_nom'] = userMap[item[agentField]];
    }
    return item;
  });
}

exports.handler = async (event, context) => {
  // CORS preflight
  if (event.httpMethod === 'OPTIONS') {
    return {
      statusCode: 200,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, PUT, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization',
        'Access-Control-Allow-Credentials': 'true'
      },
      body: ''
    };
  }

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
  
  // Récupérer l'utilisateur depuis les cookies ou le body
  // Pour Netlify, on utilise le body pour passer l'user_id (pas de session PHP)
  let userId = null;
  let userRole = null;
  
  try {
    if (event.body) {
      const body = JSON.parse(event.body);
      userId = body._user_id || null;
      userRole = body._user_role || null;
    }
  } catch (e) {
    // Ignorer
  }

  // GET: Récupérer les signalements
  if (event.httpMethod === 'GET') {
    // Pour GET, récupérer userId et userRole depuis query string
    if (!userId && event.queryStringParameters?._user_id) {
      userId = event.queryStringParameters._user_id;
    }
    if (!userRole && event.queryStringParameters?._user_role) {
      userRole = event.queryStringParameters._user_role;
    }
    
    try {
      let query = supabase.from('signalements').select('*');
      
      if (userRole === 'agent' || userRole === 'manager' || userRole === 'superadmin') {
        // Les agents/managers voient tous les signalements
        query = query.order('date_signalement', { ascending: false }).limit(50);
      } else {
        // Les citoyens voient seulement leurs signalements
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
        query = query.eq('utilisateur_id', userId).order('date_signalement', { ascending: false });
      }
      
      const { data: signalements, error } = await query;
      
      if (error) throw error;
      
      // Enrichir avec les noms
      const enriched = await enrichWithUserNames(supabase, signalements || []);
      
      // Formater les données
      const formatted = enriched.map(sig => {
        const dateField = sig.date_signalement || sig.date_creation || new Date().toISOString().split('T')[0];
        const year = new Date(dateField).getFullYear();
        return {
          ...sig,
          id_formate: 'SIG' + year + '-' + String(sig.id).padStart(6, '0'),
          date_creation: sig.date_signalement || sig.date_creation,
          photo: sig.photo_url || sig.photo
        };
      });
      
      return {
        statusCode: 200,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: true, data: formatted, message: 'Signalements récupérés' })
      };
    } catch (error) {
      console.error('Erreur récupération signalements:', error);
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
  
  // POST: Créer un signalement
  if (event.httpMethod === 'POST') {
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
      
      if (!data.type || !data.sous_type || !data.description) {
        return {
          statusCode: 400,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: false, message: 'Champs manquants' })
        };
      }
      
      // Assigner automatiquement à un agent disponible
      const { data: agents } = await supabase
        .from('utilisateurs')
        .select('id')
        .eq('role', 'agent')
        .eq('statut', 'actif')
        .limit(10);
      
      let agentAssignéId = null;
      if (agents && agents.length > 0) {
        agentAssignéId = agents[Math.floor(Math.random() * agents.length)].id;
      }
      
      const signalementData = {
        utilisateur_id: userId,
        type: data.type,
        sous_type: data.sous_type,
        description: data.description,
        localisation: data.localisation || null,
        latitude: data.latitude ? parseFloat(data.latitude) : null,
        longitude: data.longitude ? parseFloat(data.longitude) : null,
        photo_url: data.photo || null,
        statut: 'en_attente',
        agent_assigné_id: agentAssignéId,
        date_signalement: new Date().toISOString()
      };
      
      const { data: newSignalement, error } = await supabase
        .from('signalements')
        .insert(signalementData)
        .select()
        .single();
      
      if (error) throw error;
      
      // Enrichir avec les noms
      const enriched = await enrichWithUserNames(supabase, [newSignalement]);
      const signalement = enriched[0];
      
      const year = new Date().getFullYear();
      signalement.id_formate = 'SIG' + year + '-' + String(signalement.id).padStart(6, '0');
      signalement.date_creation = signalement.date_signalement || signalement.date_creation;
      signalement.photo = signalement.photo_url || signalement.photo;
      
      return {
        statusCode: 200,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: true, data: signalement, message: 'Signalement créé avec succès' })
      };
    } catch (error) {
      console.error('Erreur création signalement:', error);
      return {
        statusCode: 500,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Erreur lors de la création du signalement' })
      };
    }
  }
  
  // PUT: Mettre à jour un signalement
  if (event.httpMethod === 'PUT') {
    if (!userId || !['agent', 'manager', 'superadmin'].includes(userRole)) {
      return {
        statusCode: 403,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Non autorisé' })
      };
    }
    
    try {
      const data = JSON.parse(event.body);
      
      if (!data.id || !data.statut) {
        return {
          statusCode: 400,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: false, message: 'Champs manquants: id, statut' })
        };
      }
      
      const updateData = { statut: data.statut };
      if (data.statut === 'resolu') {
        updateData.date_modification = new Date().toISOString();
        updateData.date_resolution = new Date().toISOString();
      }
      
      const { error } = await supabase
        .from('signalements')
        .update(updateData)
        .eq('id', data.id);
      
      if (error) throw error;
      
      // Récupérer le signalement mis à jour
      const { data: signalement, error: getError } = await supabase
        .from('signalements')
        .select('*')
        .eq('id', data.id)
        .single();
      
      if (getError) throw getError;
      
      const enriched = await enrichWithUserNames(supabase, [signalement]);
      const formatted = enriched[0];
      
      const dateField = formatted.date_signalement || formatted.date_creation || new Date().toISOString().split('T')[0];
      const year = new Date(dateField).getFullYear();
      formatted.id_formate = 'SIG' + year + '-' + String(formatted.id).padStart(6, '0');
      formatted.date_creation = formatted.date_signalement || formatted.date_creation;
      formatted.photo = formatted.photo_url || formatted.photo;
      
      return {
        statusCode: 200,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: true, data: formatted, message: 'Statut mis à jour' })
      };
    } catch (error) {
      console.error('Erreur mise à jour signalement:', error);
      return {
        statusCode: 500,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: false, message: 'Erreur lors de la mise à jour' })
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

