// Netlify Function pour le budget municipal
const { createClient } = require('@supabase/supabase-js');

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

  // GET: Récupérer le budget
  if (event.httpMethod === 'GET') {
    try {
      // Consultation réservée aux utilisateurs connectés
      if (!userId || !userRole) {
        return {
          statusCode: 401,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Connexion requise pour consulter le budget' })
        };
      }
      
      // Vérifier que l'utilisateur a le droit de consulter
      const allowedRoles = ['citoyen', 'commercant', 'chef_quartier', 'agent', 'manager', 'superadmin'];
      if (!allowedRoles.includes(userRole)) {
        return {
          statusCode: 403,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Non autorisé' })
        };
      }
      
      const queryParams = event.queryStringParameters || {};
      const exercice = parseInt(queryParams.exercice) || new Date().getFullYear();
      
      // Récupérer les postes budgétaires pour l'exercice
      const { data: postes, error } = await supabase
        .from('budget_municipal')
        .select('*')
        .eq('exercice_budgetaire', exercice)
        .order('categorie', { ascending: true })
        .order('poste_budgetaire', { ascending: true });
      
      if (error) throw error;
      
      // Calculer les totaux
      let totalBudget = 0;
      let totalDepenses = 0;
      
      const formattedPostes = (postes || []).map(poste => {
        const budgetInitial = parseFloat(poste.budget_initial || 0);
        const budgetRectificatif = parseFloat(poste.budget_rectificatif || 0);
        const depensesEngagees = parseFloat(poste.depenses_engagees || 0);
        
        totalBudget += budgetInitial + budgetRectificatif;
        totalDepenses += depensesEngagees;
        
        // Calculer le taux d'exécution par poste
        const budgetTotal = budgetInitial + budgetRectificatif;
        const tauxExecution = budgetTotal > 0 ? Math.round((depensesEngagees / budgetTotal) * 100 * 10) / 10 : 0;
        
        return {
          ...poste,
          taux_execution: tauxExecution
        };
      });
      
      const tauxExecutionGlobal = totalBudget > 0 ? Math.round((totalDepenses / totalBudget) * 100 * 10) / 10 : 0;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({
          success: true,
          data: {
            exercice: exercice,
            postes: formattedPostes,
            total_budget: totalBudget,
            total_depenses: totalDepenses,
            taux_execution_global: tauxExecutionGlobal
          },
          message: 'Budget récupéré'
        })
      };
    } catch (error) {
      console.error('Erreur récupération budget:', error);
      return {
        statusCode: 500,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Erreur serveur: ' + (error.message || 'Erreur inconnue') })
      };
    }
  }
  
  // POST: Créer/modifier budget (admin uniquement)
  if (event.httpMethod === 'POST') {
    try {
      if (!userId || !userRole) {
        return {
          statusCode: 401,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Non authentifié' })
        };
      }
      
      // Seuls les managers et superadmins peuvent modifier le budget
      if (!['manager', 'superadmin'].includes(userRole)) {
        return {
          statusCode: 403,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: false, message: 'Non autorisé' })
        };
      }
      
      const body = JSON.parse(event.body);
      
      if (body.id) {
        // Mise à jour
        const updateData = {};
        if (body.budget_rectificatif !== undefined) {
          updateData.budget_rectificatif = parseFloat(body.budget_rectificatif);
        }
        if (body.depenses_engagees !== undefined) {
          updateData.depenses_engagees = parseFloat(body.depenses_engagees);
        }
        
        if (Object.keys(updateData).length === 0) {
          return {
            statusCode: 400,
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
            body: JSON.stringify({ success: false, message: 'Aucune donnée à mettre à jour' })
          };
        }
        
        const { data: updated, error: updateError } = await supabase
          .from('budget_municipal')
          .update(updateData)
          .eq('id', body.id)
          .select('*')
          .single();
        
        if (updateError) throw updateError;
        
        return {
          statusCode: 200,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: true, data: { id: updated.id }, message: 'Budget mis à jour' })
        };
      } else {
        // Création
        const required = ['exercice_budgetaire', 'poste_budgetaire', 'categorie', 'budget_initial'];
        for (const field of required) {
          if (!body[field]) {
            return {
              statusCode: 400,
              headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
              body: JSON.stringify({ success: false, message: `Champ manquant: ${field}` })
            };
          }
        }
        
        const budgetData = {
          exercice_budgetaire: parseInt(body.exercice_budgetaire),
          poste_budgetaire: body.poste_budgetaire,
          categorie: body.categorie,
          budget_initial: parseFloat(body.budget_initial),
          budget_rectificatif: parseFloat(body.budget_rectificatif || 0),
          depenses_engagees: parseFloat(body.depenses_engagees || 0)
        };
        
        const { data: newBudget, error: insertError } = await supabase
          .from('budget_municipal')
          .insert(budgetData)
          .select('*')
          .single();
        
        if (insertError) throw insertError;
        
        return {
          statusCode: 200,
          headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
          body: JSON.stringify({ success: true, data: { id: newBudget.id }, message: 'Budget créé' })
        };
      }
    } catch (error) {
      console.error('Erreur création/modification budget:', error);
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



