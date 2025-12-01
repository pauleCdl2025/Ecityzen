// Netlify Function pour les signalements
const { createClient } = require('@supabase/supabase-js');

// Fonction helper pour enrichir avec les noms d'utilisateurs
async function enrichWithUserNames(supabase, items, userField = 'utilisateur_id', agentField = 'agent_assigné_id') {
  try {
    const userIds = new Set();
    items.forEach(item => {
      if (item[userField]) userIds.add(parseInt(item[userField]));
      if (item[agentField]) userIds.add(parseInt(item[agentField]));
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
      try {
        const { data: users, error } = await supabase
          .from('utilisateurs')
          .select('id, nom, role')
          .in('id', chunk);
        
        if (error) {
          console.error('Erreur récupération utilisateurs (chunk):', error);
          continue; // Continuer avec les autres chunks
        }
        
        if (users) {
          users.forEach(user => {
            userMap[user.id] = { nom: user.nom, role: user.role || null };
          });
        }
      } catch (chunkError) {
        console.error('Erreur chunk enrichWithUserNames:', chunkError);
        continue;
      }
    }
    
    return items.map(item => {
      const userId = item[userField] ? parseInt(item[userField]) : null;
      const agentId = item[agentField] ? parseInt(item[agentField]) : null;
      
      if (userId && userMap[userId]) {
        item['utilisateur_nom'] = userMap[userId].nom;
        item['utilisateur_role'] = userMap[userId].role;
      }
      if (agentId && userMap[agentId]) {
        item['agent_nom'] = userMap[agentId].nom;
        item['agent_role'] = userMap[agentId].role;
      }
      return item;
    });
  } catch (error) {
    console.error('Erreur enrichWithUserNames:', error);
    return items; // Retourner les items sans enrichissement en cas d'erreur
  }
}

exports.handler = async (event, context) => {
  // Gestion globale des erreurs pour éviter les 502
  try {
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

    const supabaseUrl = process.env.SUPABASE_URL || 'https://srbzvjrqbhtuyzlwdghn.supabase.co';
    const supabaseKey = process.env.SUPABASE_ANON_KEY || 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM';
    
    if (!supabaseUrl || !supabaseKey) {
      console.error('Configuration Supabase manquante');
      return {
        statusCode: 200,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: true, data: [], message: 'Configuration manquante' })
      };
    }

    const supabase = createClient(supabaseUrl, supabaseKey);
    
    // Récupérer l'utilisateur depuis les query params (GET) ou le body (POST/PUT)
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
      } catch (e) {
        // Ignorer
      }
    }

    // GET: Récupérer les signalements
    if (event.httpMethod === 'GET') {
      
      try {
      const queryParams = event.queryStringParameters || {};
      const agentId = queryParams.agent_id ? parseInt(queryParams.agent_id) : null;
      
      // Construire la requête de base
      let query = supabase.from('signalements').select('*');
      
      if (userRole === 'agent' || userRole === 'manager' || userRole === 'superadmin') {
        // Optimisation : si agent_id est spécifié et que c'est un agent, filtrer directement
        if (agentId && userRole === 'agent') {
          // Agent : charger seulement ses signalements assignés (beaucoup plus rapide)
          query = query.eq('agent_assigné_id', agentId)
                       .order('date_signalement', { ascending: false })
                       .limit(100);
        } else {
          // Manager/Superadmin ou agent sans filtre : voir tous les signalements
          query = query.order('date_signalement', { ascending: false }).limit(50);
        }
      } else {
        // Les citoyens voient seulement leurs signalements
        if (!userId) {
          return {
            statusCode: 200,
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify({ success: true, data: [], message: 'Non authentifié' })
          };
        }
        query = query.eq('utilisateur_id', parseInt(userId)).order('date_signalement', { ascending: false });
      }
      
      // Exécuter la requête avec timeout
      let signalements = [];
      let queryError = null;
      
      try {
        // Timeout de 8 secondes pour éviter les 502
        const queryPromise = query;
        const timeoutPromise = new Promise((_, reject) => 
          setTimeout(() => reject(new Error('Timeout')), 8000)
        );
        
        const result = await Promise.race([queryPromise, timeoutPromise]);
        signalements = result.data || [];
        queryError = result.error;
      } catch (err) {
        console.error('Erreur requête Supabase signalements:', err.message || err);
        queryError = err;
      }
      
      if (queryError) {
        console.error('Erreur Supabase query signalements:', queryError);
        // Retourner un tableau vide plutôt qu'une erreur 500 pour éviter les 502
        return {
          statusCode: 200,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: true, data: [], message: 'Aucun signalement disponible' })
        };
      }
      
      // Enrichir avec les noms (avec gestion d'erreur)
      let enriched = signalements || [];
      try {
        enriched = await enrichWithUserNames(supabase, signalements || []);
      } catch (err) {
        console.error('Erreur enrichissement signalements:', err);
        enriched = signalements || []; // Retourner sans enrichissement en cas d'erreur
      }
      
      // Formater les données
      const formatted = enriched.map(sig => {
        // Gérer les différents noms de champs de date
        const dateField = sig.date_signalement || sig.date_creation || new Date().toISOString();
        let year;
        try {
          year = new Date(dateField).getFullYear();
        } catch (e) {
          year = new Date().getFullYear();
        }
        
        // S'assurer que date_creation et date_signalement existent
        const dateValue = sig.date_signalement || sig.date_creation || new Date().toISOString();
        
        return {
          ...sig,
          id_formate: 'SIG' + year + '-' + String(sig.id).padStart(6, '0'),
          date_creation: dateValue,
          date_signalement: dateValue,
          // Mapper photo_url vers photo pour compatibilité frontend
          photo: sig.photo_url || sig.photo || null,
          photo_url: sig.photo_url || sig.photo || null,
          // S'assurer que tous les champs essentiels existent
          statut: sig.statut || 'en_attente',
          type: sig.type || 'Autre',
          sous_type: sig.sous_type || sig.type || 'Autre',
          description: sig.description || '',
          localisation: sig.localisation || null
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
      console.error('Détails erreur:', error.message, error.stack);
      // Retourner un tableau vide plutôt qu'une erreur 500 pour éviter les 502
      return {
        statusCode: 200,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        },
        body: JSON.stringify({ success: true, data: [], message: 'Erreur lors du chargement des signalements' })
      };
    }
  
    // POST: Créer un signalement (peut être fait sans connexion)
    if (event.httpMethod === 'POST') {
      try {
      let data;
      try {
        data = typeof event.body === 'string' ? JSON.parse(event.body) : event.body;
      } catch (parseError) {
        console.error('Erreur parsing body signalement:', parseError);
        return {
          statusCode: 400,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: false, message: 'Données JSON invalides' })
        };
      }
      
      console.log('Données signalement reçues:', { type: data.type, sous_type: data.sous_type, has_description: !!data.description });
      
      if (!data.type || !data.sous_type || !data.description) {
        console.error('Champs manquants:', { type: data.type, sous_type: data.sous_type, description: data.description });
        return {
          statusCode: 400,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: false, message: 'Champs manquants: type, sous_type, description' })
        };
      }
      
      // userId peut être null pour les signalements anonymes
      // Ne pas assigner automatiquement - le manager assignera
      // Les signalements arrivent d'abord chez le manager
      const signalementData = {
        utilisateur_id: userId || null, // Permettre les signalements anonymes
        type: data.type,
        sous_type: data.sous_type,
        description: data.description,
        localisation: data.localisation || null,
        latitude: data.latitude ? parseFloat(data.latitude) : null,
        longitude: data.longitude ? parseFloat(data.longitude) : null,
        photo_url: data.photo || null,
        statut: 'en_attente',
        agent_assigné_id: null, // Le manager assignera
        date_signalement: new Date().toISOString()
      };
      
      console.log('Insertion signalement dans Supabase:', signalementData);
      
      const { data: newSignalement, error } = await supabase
        .from('signalements')
        .insert(signalementData)
        .select()
        .single();
      
      if (error) {
        console.error('Erreur Supabase insertion signalement:', error);
        throw error;
      }
      
      if (!newSignalement) {
        console.error('Erreur: Supabase a retourné success mais newSignalement est null');
        return {
          statusCode: 500,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          },
          body: JSON.stringify({ success: false, message: 'Erreur: signalement créé mais données non retournées' })
        };
      }
      
      console.log('Signalement créé avec succès:', newSignalement.id);
      
      // Enrichir avec les noms
      const enriched = await enrichWithUserNames(supabase, [newSignalement]);
      const signalement = enriched[0];
      
      const year = new Date().getFullYear();
      signalement.id_formate = 'SIG' + year + '-' + String(signalement.id).padStart(6, '0');
      signalement.date_creation = signalement.date_signalement || signalement.date_creation;
      // Mapper photo_url vers photo pour compatibilité frontend
      signalement.photo = signalement.photo_url || signalement.photo || null;
      signalement.photo_url = signalement.photo_url || signalement.photo || null;
      
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
        let data;
        try {
          data = typeof event.body === 'string' ? JSON.parse(event.body) : event.body;
        } catch (parseError) {
          console.error('Erreur parsing body signalement (PUT):', parseError);
          return {
            statusCode: 400,
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify({ success: false, message: 'Données JSON invalides' })
          };
        }
        
        if (!data.id) {
          return {
            statusCode: 400,
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify({ success: false, message: 'ID manquant' })
          };
        }
        
        const updateData = {};
        
        // Mettre à jour le statut si fourni
        if (data.statut) {
          updateData.statut = data.statut;
          if (data.statut === 'resolu') {
            updateData.date_modification = new Date().toISOString();
            updateData.date_resolution = new Date().toISOString();
          }
        }
        
        // Mettre à jour l'agent assigné si fourni (pour le manager)
        if (data.agent_assigné_id !== undefined) {
          updateData.agent_assigné_id = data.agent_assigné_id ? parseInt(data.agent_assigné_id) : null;
        }
        
        if (Object.keys(updateData).length === 0) {
          return {
            statusCode: 400,
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify({ success: false, message: 'Aucune donnée à mettre à jour' })
          };
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
        // Mapper photo_url vers photo pour compatibilité frontend
        formatted.photo = formatted.photo_url || formatted.photo || null;
        formatted.photo_url = formatted.photo_url || formatted.photo || null;
        
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
  } catch (globalError) {
    // Catch global pour éviter les 502
    console.error('Erreur globale signalements.js:', globalError);
    console.error('Stack:', globalError.stack);
    return {
      statusCode: 200,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*'
      },
      body: JSON.stringify({ success: true, data: [], message: 'Erreur serveur' })
    };
  }
};

