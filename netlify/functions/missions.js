// Netlify Function pour les missions agents
const { createClient } = require('@supabase/supabase-js');

async function enrichWithUserNames(supabase, items, userField = 'agent_id') {
  const userIds = new Set();
  items.forEach(item => {
    if (item[userField]) userIds.add(item[userField]);
  });
  
  if (userIds.size === 0) return items;
  
  const { data: users } = await supabase
    .from('utilisateurs')
    .select('id, nom')
    .in('id', Array.from(userIds));
  
  const userMap = {};
  if (users) {
    users.forEach(user => {
      userMap[user.id] = user.nom;
    });
  }
  
  return items.map(item => {
    if (item[userField] && userMap[item[userField]]) {
      item['agent_nom'] = userMap[item[userField]];
    }
    return item;
  });
}

exports.handler = async (event, context) => {
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
      headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
      body: JSON.stringify({ success: false, message: 'Configuration Supabase manquante' })
    };
  }

  const supabase = createClient(supabaseUrl, supabaseKey);
  
  let userId = null;
  let userRole = null;
  
  try {
    if (event.body) {
      const body = JSON.parse(event.body);
      userId = body._user_id || null;
      userRole = body._user_role || null;
    }
  } catch (e) {}

  // GET: Récupérer les missions
  if (event.httpMethod === 'GET') {
    // Pour GET, récupérer userId et userRole depuis query string
    if (!userId && event.queryStringParameters?._user_id) {
      userId = event.queryStringParameters._user_id;
    }
    if (!userRole && event.queryStringParameters?._user_role) {
      userRole = event.queryStringParameters._user_role;
    }
    
    if (!userId || !['agent', 'manager', 'superadmin'].includes(userRole)) {
      return {
        statusCode: 401,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Non autorisé' })
      };
    }
    
    try {
      let query = supabase.from('missions').select('*');
      
      if (userRole === 'agent') {
        // Les agents voient seulement leurs missions
        query = query.eq('agent_id', userId);
      }
      
      query = query.order('date_mission', { ascending: true });
      
      const { data: missions, error } = await query;
      
      if (error) throw error;
      
      // Enrichir avec les noms d'agents si manager
      let enriched = missions || [];
      if (userRole !== 'agent') {
        enriched = await enrichWithUserNames(supabase, enriched);
      }
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: enriched, message: 'Missions récupérées' })
      };
    } catch (error) {
      console.error('Erreur récupération missions:', error);
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: [], message: 'Aucune mission disponible' })
      };
    }
  }
  
  // POST: Créer une mission (manager/superadmin)
  if (event.httpMethod === 'POST') {
    if (!userId || !['manager', 'superadmin'].includes(userRole)) {
      return {
        statusCode: 403,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Non autorisé' })
      };
    }
    
    try {
      const data = JSON.parse(event.body);
      
      if (!data.agent_id || !data.titre || !data.description) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Champs manquants: agent_id, titre, description' })
        };
      }
      
      const missionData = {
        agent_id: data.agent_id,
        titre: data.titre,
        description: data.description,
        localisation: data.localisation || null,
        latitude: data.latitude ? parseFloat(data.latitude) : null,
        longitude: data.longitude ? parseFloat(data.longitude) : null,
        statut: data.statut || 'assignee',
        date_mission: data.date_mission || null
      };
      
      const { data: newMission, error } = await supabase
        .from('missions')
        .insert(missionData)
        .select()
        .single();
      
      if (error) throw error;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: newMission, message: 'Mission créée avec succès' })
      };
    } catch (error) {
      console.error('Erreur création mission:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur lors de la création' })
      };
    }
  }
  
  // PUT: Mettre à jour une mission
  if (event.httpMethod === 'PUT') {
    if (!userId || !['agent', 'manager', 'superadmin'].includes(userRole)) {
      return {
        statusCode: 403,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Non autorisé' })
      };
    }
    
    try {
      const data = JSON.parse(event.body);
      
      if (!data.id || !data.statut) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Champs manquants: id, statut' })
        };
      }
      
      // Vérifier que l'agent ne peut modifier que ses propres missions
      if (userRole === 'agent') {
        const { data: mission } = await supabase
          .from('missions')
          .select('agent_id')
          .eq('id', data.id)
          .single();
        
        if (!mission || mission.agent_id != userId) {
          return {
            statusCode: 403,
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
            body: JSON.stringify({ success: false, message: 'Non autorisé' })
          };
        }
      }
      
      const { error } = await supabase
        .from('missions')
        .update({ statut: data.statut })
        .eq('id', data.id);
      
      if (error) throw error;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, message: 'Statut mis à jour' })
      };
    } catch (error) {
      console.error('Erreur mise à jour mission:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur lors de la mise à jour' })
      };
    }
  }
  
  return {
    statusCode: 405,
    headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
    body: JSON.stringify({ success: false, message: 'Méthode non autorisée' })
  };
};

