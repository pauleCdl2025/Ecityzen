// Netlify Function pour les demandes administratives
const { createClient } = require('@supabase/supabase-js');

async function enrichWithUserNames(supabase, items) {
  const userIds = new Set();
  items.forEach(item => {
    if (item.utilisateur_id) userIds.add(item.utilisateur_id);
    if (item.agent_assigné_id) userIds.add(item.agent_assigné_id);
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
    if (item.utilisateur_id && userMap[item.utilisateur_id]) {
      item.utilisateur_nom = userMap[item.utilisateur_id];
    }
    if (item.agent_assigné_id && userMap[item.agent_assigné_id]) {
      item.agent_nom = userMap[item.agent_assigné_id];
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

  // GET: Récupérer les demandes
  if (event.httpMethod === 'GET') {
    // Pour GET, récupérer userId et userRole depuis query string
    if (!userId && event.queryStringParameters?._user_id) {
      userId = event.queryStringParameters._user_id;
    }
    if (!userRole && event.queryStringParameters?._user_role) {
      userRole = event.queryStringParameters._user_role;
    }
    
    try {
      let query = supabase.from('demandes').select('*');
      
      if (userRole === 'agent' || userRole === 'manager' || userRole === 'superadmin') {
        query = query.order('date_creation', { ascending: false }).limit(50);
      } else {
        if (!userId) {
          return {
            statusCode: 401,
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
            body: JSON.stringify({ success: false, message: 'Non authentifié' })
          };
        }
        query = query.eq('utilisateur_id', userId).order('date_creation', { ascending: false });
      }
      
      const { data: demandes, error } = await query;
      
      if (error) throw error;
      
      // Décoder les documents JSON et formater les données
      const formatted = (demandes || []).map(d => {
        if (d.documents && typeof d.documents === 'string') {
          try {
            d.documents = JSON.parse(d.documents);
          } catch (e) {
            d.documents = [];
          }
        }
        
        // Générer numero_dossier si absent
        if (!d.numero_dossier && d.id) {
          const year = d.date_creation ? new Date(d.date_creation).getFullYear() : new Date().getFullYear();
          d.numero_dossier = 'DEM-' + year + '-' + String(d.id).padStart(6, '0');
        }
        
        // S'assurer que motif existe
        if (!d.motif) {
          d.motif = d.motif || null;
        }
        
        return d;
      });
      
      const enriched = await enrichWithUserNames(supabase, formatted);
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: enriched, message: 'Demandes récupérées' })
      };
    } catch (error) {
      console.error('Erreur récupération demandes:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur serveur' })
      };
    }
  }
  
  // POST: Créer une demande
  if (event.httpMethod === 'POST') {
    if (!userId) {
      return {
        statusCode: 401,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Non authentifié' })
      };
    }
    
    try {
      const data = JSON.parse(event.body);
      
      if (!data.type || !data.service || data.cout === undefined) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Champs manquants: type, service, cout' })
        };
      }
      
      // Assigner automatiquement à un agent
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
      
      const demandeData = {
        utilisateur_id: userId,
        type: data.type,
        service: data.service,
        motif: data.motif || null,
        montant: parseFloat(data.cout),
        statut: 'en_attente',
        agent_assigné_id: agentAssignéId,
        date_creation: new Date().toISOString()
      };
      
      // Ajouter les documents si présents (peuvent être en base64)
      if (data.documents && Array.isArray(data.documents)) {
        // Les documents peuvent être en base64 (production) ou déjà formatés (local)
        demandeData.documents = JSON.stringify(data.documents);
      }
      
      const { data: newDemande, error } = await supabase
        .from('demandes')
        .insert(demandeData)
        .select()
        .single();
      
      if (error) throw error;
      
      // Décoder les documents pour la réponse
      if (newDemande.documents && typeof newDemande.documents === 'string') {
        try {
          newDemande.documents = JSON.parse(newDemande.documents);
        } catch (e) {
          newDemande.documents = [];
        }
      }
      
      // Générer numero_dossier
      const year = new Date().getFullYear();
      newDemande.numero_dossier = 'DEM-' + year + '-' + String(newDemande.id).padStart(6, '0');
      
      // S'assurer que motif existe
      if (!newDemande.motif) {
        newDemande.motif = demandeData.motif || null;
      }
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: newDemande, message: 'Demande créée avec succès' })
      };
    } catch (error) {
      console.error('Erreur création demande:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur lors de la création de la demande' })
      };
    }
  }
  
  // PUT: Mettre à jour une demande
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
          body: JSON.stringify({ success: false, message: 'Champs manquants' })
        };
      }
      
      const updateData = { statut: data.statut };
      if (data.statut === 'valide') {
        updateData.date_validation = new Date().toISOString();
      }
      
      const { data: updated, error } = await supabase
        .from('demandes')
        .update(updateData)
        .eq('id', data.id)
        .select()
        .single();
      
      if (error) throw error;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: updated, message: 'Statut mis à jour' })
      };
    } catch (error) {
      console.error('Erreur mise à jour demande:', error);
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

