// Netlify Function pour les notifications
const { createClient } = require('@supabase/supabase-js');

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

  // GET: Récupérer les notifications
  if (event.httpMethod === 'GET') {
    // Pour GET, récupérer userId depuis query string
    if (!userId && event.queryStringParameters?._user_id) {
      userId = event.queryStringParameters._user_id;
    }
    
    if (!userId) {
      return {
        statusCode: 401,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Non authentifié' })
      };
    }
    
    try {
      const { data: notifications, error } = await supabase
        .from('notifications')
        .select('*')
        .eq('utilisateur_id', userId)
        .order('date_envoi', { ascending: false })
        .limit(50);
      
      if (error) throw error;
      
      // Compter les non lues
      const nonLues = (notifications || []).filter(n => 
        !n.statut_lecture || n.statut_lecture === 'non_lu'
      ).length;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({
          success: true,
          data: {
            notifications: notifications || [],
            non_lues: nonLues
          },
          message: 'Notifications récupérées'
        })
      };
    } catch (error) {
      console.error('Erreur récupération notifications:', error);
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: { notifications: [], non_lues: 0 }, message: 'Aucune notification' })
      };
    }
  }
  
  // POST: Créer une notification (admin)
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
      
      if (!data.titre || !data.message) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Champs manquants: titre, message' })
        };
      }
      
      const notificationData = {
        utilisateur_id: data.utilisateur_id || userId,
        titre: data.titre,
        message: data.message,
        categorie: data.categorie || 'informative',
        statut_lecture: 'non_lu',
        date_envoi: new Date().toISOString()
      };
      
      const { data: newNotification, error } = await supabase
        .from('notifications')
        .insert(notificationData)
        .select()
        .single();
      
      if (error) throw error;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: newNotification, message: 'Notification créée' })
      };
    } catch (error) {
      console.error('Erreur création notification:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur lors de la création de la notification' })
      };
    }
  }
  
  // PUT: Marquer comme lu
  if (event.httpMethod === 'PUT') {
    if (!userId) {
      return {
        statusCode: 401,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Non authentifié' })
      };
    }
    
    try {
      const data = JSON.parse(event.body);
      
      if (!data.id) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'ID notification manquant' })
        };
      }
      
      const { error } = await supabase
        .from('notifications')
        .update({ statut_lecture: 'lu' })
        .eq('id', data.id)
        .eq('utilisateur_id', userId);
      
      if (error) throw error;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, message: 'Notification marquée comme lue' })
      };
    } catch (error) {
      console.error('Erreur mise à jour notification:', error);
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

