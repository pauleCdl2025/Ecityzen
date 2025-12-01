// Netlify Function pour les paiements
const { createClient } = require('@supabase/supabase-js');

async function enrichWithUserNames(supabase, items) {
  const userIds = new Set();
  items.forEach(item => {
    if (item.utilisateur_id) userIds.add(item.utilisateur_id);
  });
  
  if (userIds.size === 0) return items;
  
  const { data: users, error } = await supabase
    .from('utilisateurs')
    .select('id, nom, role')
    .in('id', Array.from(userIds));
  
  if (error) {
    console.error('Erreur récupération utilisateurs:', error);
    return items;
  }
  
  const userMap = {};
  if (users) {
    users.forEach(user => {
      userMap[user.id] = { nom: user.nom, role: user.role };
    });
  }
  
  return items.map(item => {
    if (item.utilisateur_id && userMap[item.utilisateur_id]) {
      item.utilisateur_nom = userMap[item.utilisateur_id].nom;
      item.utilisateur_role = userMap[item.utilisateur_id].role;
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
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization',
        'Access-Control-Allow-Credentials': 'true'
      },
      body: ''
    };
  }

  const supabaseUrl = process.env.SUPABASE_URL || 'https://srbzvjrqbhtuyzlwdghn.supabase.co';
  const supabaseKey = process.env.SUPABASE_ANON_KEY || 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM';
  
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
  
  // Pour GET, récupérer depuis query string
  if (event.httpMethod === 'GET' && event.queryStringParameters) {
    userId = event.queryStringParameters._user_id || null;
    userRole = event.queryStringParameters._user_role || null;
  }
  
  // Pour POST, récupérer depuis le body
  if (event.httpMethod === 'POST' && event.body) {
    try {
      const body = JSON.parse(event.body);
      userId = body._user_id || null;
      userRole = body._user_role || null;
    } catch (e) {
      console.error('Erreur parsing body:', e);
    }
  }

  // GET: Récupérer les paiements
  if (event.httpMethod === 'GET') {
    try {
      const queryParams = event.queryStringParameters || {};
      const limit = Math.min(parseInt(queryParams.limit) || 50, 100);
      const offset = Math.max(parseInt(queryParams.offset) || 0, 0);
      const statut = queryParams.statut || null;
      const date_debut = queryParams.date_debut || null;
      const date_fin = queryParams.date_fin || null;
      
      let query = supabase.from('paiements').select('*');
      
      // Gestion des rôles
      if (userRole === 'manager' || userRole === 'superadmin' || userRole === 'hopital') {
        // Les managers, superadmins et hôpitaux voient tous les paiements
      } else {
        // Les autres utilisateurs voient seulement leurs paiements
        if (!userId) {
          return {
            statusCode: 401,
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
            body: JSON.stringify({ success: false, message: 'Non authentifié' })
          };
        }
        query = query.eq('utilisateur_id', userId);
      }
      
      // Filtres optionnels
      if (statut) {
        query = query.eq('statut', statut);
      }
      
      // Tri et limite
      query = query.order('date_paiement', { ascending: false }).limit(limit);
      
      const { data: paiements, error } = await query;
      
      if (error) throw error;
      
      // Filtrer par date si nécessaire
      let filtered = paiements || [];
      if (date_debut) {
        filtered = filtered.filter(p => {
          const datePaiement = (p.date_paiement || '').substring(0, 10);
          return datePaiement >= date_debut;
        });
      }
      if (date_fin) {
        filtered = filtered.filter(p => {
          const datePaiement = (p.date_paiement || '').substring(0, 10);
          return datePaiement <= date_fin;
        });
      }
      
      // Appliquer offset
      if (offset > 0) {
        filtered = filtered.slice(offset);
      }
      filtered = filtered.slice(0, limit);
      
      // Enrichir avec les noms d'utilisateurs
      const enriched = await enrichWithUserNames(supabase, filtered);
      
      // Formater les références
      const formatted = enriched.map(p => {
        if (!p.reference_transaction) {
          const dateField = p.date_paiement || new Date().toISOString();
          const year = new Date(dateField).getFullYear();
          p.reference_transaction = 'PAY' + year + '-' + String(p.id).padStart(6, '0');
        }
        // Compatibilité avec l'ancien format
        p.reference_paiement = p.reference_transaction || '';
        p.methode = p.mode_paiement || 'espece';
        return p;
      });
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: formatted, message: 'Paiements récupérés' })
      };
    } catch (error) {
      console.error('Erreur récupération paiements:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur serveur: ' + (error.message || 'Erreur inconnue') })
      };
    }
  }
  
  // POST: Créer un paiement
  if (event.httpMethod === 'POST') {
    try {
      if (!userId) {
        return {
          statusCode: 401,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Non authentifié' })
        };
      }
      
      const body = JSON.parse(event.body);
      
      if (!body.montant || (!body.mode_paiement && !body.methode)) {
        return {
          statusCode: 400,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Champs manquants: montant, mode_paiement' })
        };
      }
      
      const referenceTransaction = 'PAY' + new Date().getFullYear() + '-' + String(Date.now()).padStart(8, '0');
      
      const paiementData = {
        utilisateur_id: userId,
        demande_id: body.demande_id || null,
        montant: parseFloat(body.montant),
        mode_paiement: body.mode_paiement || body.methode || 'espece',
        reference_transaction: referenceTransaction,
        statut: 'en_attente'
      };
      
      const { data: newPaiement, error: insertError } = await supabase
        .from('paiements')
        .insert(paiementData)
        .select('*')
        .single();
      
      if (insertError) throw insertError;
      
      // Simuler le traitement (en production, intégrer avec les APIs de paiement)
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      // Mettre à jour le statut
      const { data: updatedPaiement, error: updateError } = await supabase
        .from('paiements')
        .update({ statut: 'confirme' })
        .eq('id', newPaiement.id)
        .select('*')
        .single();
      
      if (updateError) {
        console.error('Erreur mise à jour paiement:', updateError);
      }
      
      // Si c'est un paiement pour une demande, mettre à jour le statut
      if (body.demande_id) {
        await supabase
          .from('demandes')
          .update({ statut: 'en_traitement' })
          .eq('id', body.demande_id);
      }
      
      // Enrichir avec le nom de l'utilisateur
      const enriched = await enrichWithUserNames(supabase, [updatedPaiement || newPaiement]);
      const paiement = enriched[0];
      
      // Compatibilité
      paiement.reference_paiement = paiement.reference_transaction;
      paiement.methode = paiement.mode_paiement;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: true, data: paiement, message: 'Paiement confirmé' })
      };
    } catch (error) {
      console.error('Erreur création paiement:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur serveur: ' + (error.message || 'Erreur inconnue') })
      };
    }
  }
  
  return {
    statusCode: 405,
    headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
    body: JSON.stringify({ success: false, message: 'Méthode non autorisée' })
  };
};

