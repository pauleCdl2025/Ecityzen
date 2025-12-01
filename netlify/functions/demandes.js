// Netlify Function pour les demandes administratives
const { createClient } = require('@supabase/supabase-js');

async function enrichWithUserNames(supabase, items) {
  try {
    const userIds = new Set();
    items.forEach(item => {
      if (item.utilisateur_id) userIds.add(parseInt(item.utilisateur_id));
      if (item.agent_assigné_id) userIds.add(parseInt(item.agent_assigné_id));
    });
    
    if (userIds.size === 0) return items;
    
    // Traiter par lots de 100 pour éviter les limites Supabase
    const userIdsArray = Array.from(userIds);
    const chunks = [];
    for (let i = 0; i < userIdsArray.length; i += 100) {
      chunks.push(userIdsArray.slice(i, i + 100));
    }
    
    const userMap = {};
    
    for (const chunk of chunks) {
      const { data: users, error } = await supabase
        .from('utilisateurs')
        .select('id, nom, role')
        .in('id', chunk);
      
      if (error) {
        console.error('Erreur récupération utilisateurs:', error);
        continue; // Continuer avec les autres chunks
      }
      
      if (users) {
        users.forEach(user => {
          userMap[user.id] = { nom: user.nom, role: user.role || null };
        });
      }
    }
    
    return items.map(item => {
      const userId = item.utilisateur_id ? parseInt(item.utilisateur_id) : null;
      const agentId = item.agent_assigné_id ? parseInt(item.agent_assigné_id) : null;
      
      if (userId && userMap[userId]) {
        item.utilisateur_nom = userMap[userId].nom;
        item.utilisateur_role = userMap[userId].role;
      }
      if (agentId && userMap[agentId]) {
        item.agent_nom = userMap[agentId].nom;
        item.agent_role = userMap[agentId].role;
      }
      return item;
    });
  } catch (error) {
    console.error('Erreur enrichWithUserNames:', error);
    return items; // Retourner les items sans enrichissement en cas d'erreur
  }
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
  
  // Pour GET, récupérer d'abord depuis query string
  if (event.httpMethod === 'GET' && event.queryStringParameters) {
    userId = event.queryStringParameters._user_id || null;
    userRole = event.queryStringParameters._user_role || null;
  }
  
  // Sinon, essayer depuis le body (pour POST/PUT)
  if (!userId && event.body) {
    try {
      const body = JSON.parse(event.body);
      userId = body._user_id || null;
      userRole = body._user_role || null;
    } catch (e) {}
  }

  // GET: Récupérer les demandes
  if (event.httpMethod === 'GET') {
    
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
        query = query.eq('utilisateur_id', parseInt(userId)).order('date_creation', { ascending: false });
      }
      
      const { data: demandes, error } = await query;
      
      if (error) {
        console.error('Erreur Supabase query:', error);
        throw error;
      }
      
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
      console.error('Détails erreur:', error.message, error.stack);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur serveur: ' + (error.message || 'Erreur inconnue') })
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
      
      // Ne pas assigner automatiquement - le manager assignera
      // Les demandes arrivent d'abord chez le manager
      const demandeData = {
        utilisateur_id: userId,
        type: data.type,
        service: data.service,
        motif: data.motif || null,
        montant: parseFloat(data.cout),
        statut: 'en_attente',
        agent_assigné_id: null, // Le manager assignera
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
      
      if (!data.id) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'ID manquant' })
        };
      }
      
      const updateData = {};
      
      // Mettre à jour le statut si fourni
      if (data.statut) {
        updateData.statut = data.statut;
        if (data.statut === 'valide') {
          updateData.date_validation = new Date().toISOString();
        }
        if (data.statut === 'dossier_incomplet') {
          updateData.date_modification = new Date().toISOString();
        }
      }
      
      // Mettre à jour l'agent assigné si fourni (pour le manager)
      if (data.agent_assigné_id !== undefined) {
        updateData.agent_assigné_id = data.agent_assigné_id ? parseInt(data.agent_assigné_id) : null;
      }
      
      // Mettre à jour le commentaire si fourni
      if (data.commentaire_agent) {
        updateData.commentaire_agent = data.commentaire_agent;
      }
      
      if (Object.keys(updateData).length === 0) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Aucune donnée à mettre à jour' })
        };
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

