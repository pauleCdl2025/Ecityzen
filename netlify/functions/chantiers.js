// Netlify Function pour les chantiers de travaux publics
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

  // GET: Récupérer les chantiers
  if (event.httpMethod === 'GET') {
    try {
      const statut = event.queryStringParameters?.statut || null;
      const type = event.queryStringParameters?.type || null;
      
      let query = supabase.from('chantiers_travaux').select('*');
      
      if (statut) {
        query = query.eq('statut', statut);
      }
      if (type) {
        query = query.eq('type', type);
      }
      
      query = query.order('date_debut', { ascending: false });
      
      const { data: chantiers, error } = await query;
      
      if (error) throw error;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: chantiers || [], message: 'Chantiers récupérés' })
      };
    } catch (error) {
      console.error('Erreur récupération chantiers:', error);
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: [], message: 'Aucun chantier disponible' })
      };
    }
  }
  
  // POST: Créer un chantier (admin)
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
      
      if (!data.titre || !data.type || !data.description) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Champs manquants: titre, type, description' })
        };
      }
      
      const chantierData = {
        titre: data.titre,
        description: data.description,
        type: data.type,
        localisation: data.localisation || null,
        latitude: data.latitude ? parseFloat(data.latitude) : null,
        longitude: data.longitude ? parseFloat(data.longitude) : null,
        statut: data.statut || 'planifie',
        date_debut: data.date_debut || null,
        date_fin_prevue: data.date_fin_prevue || null,
        budget_alloue: data.budget_alloue ? parseFloat(data.budget_alloue) : null,
        entreprise: data.entreprise || null
      };
      
      const { data: newChantier, error } = await supabase
        .from('chantiers_travaux')
        .insert(chantierData)
        .select()
        .single();
      
      if (error) throw error;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: newChantier, message: 'Chantier créé avec succès' })
      };
    } catch (error) {
      console.error('Erreur création chantier:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur lors de la création' })
      };
    }
  }
  
  // PUT: Mettre à jour un chantier (admin)
  if (event.httpMethod === 'PUT') {
    if (!userId || !['manager', 'superadmin'].includes(userRole)) {
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
      const allowedFields = ['statut', 'date_fin_prevue', 'date_fin_reelle', 'description', 'budget_alloue'];
      allowedFields.forEach(field => {
        if (data[field] !== undefined) {
          updateData[field] = data[field];
        }
      });
      
      if (Object.keys(updateData).length === 0) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Aucun champ à mettre à jour' })
        };
      }
      
      const { error } = await supabase
        .from('chantiers_travaux')
        .update(updateData)
        .eq('id', data.id);
      
      if (error) throw error;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, message: 'Chantier mis à jour' })
      };
    } catch (error) {
      console.error('Erreur mise à jour chantier:', error);
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

