const { createClient } = require('@supabase/supabase-js');

const supabaseUrl = process.env.SUPABASE_URL;
const supabaseKey = process.env.SUPABASE_ANON_KEY;

if (!supabaseUrl || !supabaseKey) {
    throw new Error('Missing Supabase environment variables');
}

const supabase = createClient(supabaseUrl, supabaseKey);

// Helper function to send JSON response
function sendJSONResponse(statusCode, body) {
    return {
        statusCode,
        headers: {
            'Content-Type': 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization',
            'Access-Control-Allow-Credentials': 'true'
        },
        body: JSON.stringify(body)
    };
}

exports.handler = async (event) => {
    // Handle CORS preflight
    if (event.httpMethod === 'OPTIONS') {
        return sendJSONResponse(200, {});
    }

    if (event.httpMethod !== 'GET') {
        return sendJSONResponse(405, {
            success: false,
            message: 'Méthode non autorisée'
        });
    }

    try {
        // Extract user info from query parameters
        const userId = event.queryStringParameters?._user_id;
        const userRole = event.queryStringParameters?._user_role;

        // Check authorization (only managers and superadmins)
        if (!userId || !['manager', 'superadmin'].includes(userRole)) {
            return sendJSONResponse(401, {
                success: false,
                message: 'Non autorisé'
            });
        }

        const stats = {};
        const currentMonth = new Date().toISOString().substring(0, 7); // YYYY-MM

        // Statistiques demandes
        try {
            const { data: allDemandes, error: demandesError } = await supabase
                .from('demandes')
                .select('*');

            if (demandesError) throw demandesError;

            // Demandes ce mois
            const demandesMois = allDemandes.filter(d => 
                d.date_creation && d.date_creation.startsWith(currentMonth)
            );
            stats.demandes_mois = demandesMois.length;

            // Demandes validées
            const demandesValidees = allDemandes.filter(d => d.statut === 'valide');
            stats.demandes_validees = demandesValidees.length;

            // Calcul délai moyen
            const delais = [];
            allDemandes.forEach(d => {
                if (d.date_validation && d.date_creation) {
                    const dateCreation = new Date(d.date_creation);
                    const dateValidation = new Date(d.date_validation);
                    const diffTime = Math.abs(dateValidation - dateCreation);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    delais.push(diffDays);
                }
            });
            stats.delai_moyen = delais.length > 0 
                ? Math.round((delais.reduce((a, b) => a + b, 0) / delais.length) * 10) / 10 
                : 0;

            // Taux de satisfaction
            const totalDemandes = allDemandes.length;
            stats.taux_satisfaction = totalDemandes > 0 
                ? Math.round((demandesValidees.length / totalDemandes) * 100 * 10) / 10 
                : 0;

        } catch (e) {
            stats.demandes_mois = 0;
            stats.demandes_validees = 0;
            stats.delai_moyen = 0;
            stats.taux_satisfaction = 0;
        }

        // Statistiques paiements
        try {
            const { data: paiementsConfirmes, error: paiementsError } = await supabase
                .from('paiements')
                .select('*')
                .eq('statut', 'confirme');

            if (paiementsError) throw paiementsError;

            // Recettes ce mois
            let recettesMois = 0;
            paiementsConfirmes.forEach(p => {
                if (p.date_paiement && p.date_paiement.startsWith(currentMonth)) {
                    recettesMois += parseFloat(p.montant || 0);
                }
            });
            stats.recettes_mois = recettesMois;

        } catch (e) {
            stats.recettes_mois = 0;
        }

        // Statistiques utilisateurs
        try {
            const { data: utilisateursActifs, error: usersError } = await supabase
                .from('utilisateurs')
                .select('*')
                .eq('statut', 'actif');

            if (usersError) throw usersError;

            stats.utilisateurs_actifs = utilisateursActifs.length;

            // Agents actifs
            const agents = utilisateursActifs.filter(u => u.role === 'agent');
            stats.agents_actifs = agents.length;

            // Performance des agents
            stats.agents_performance = [];
            for (const agent of agents) {
                const { data: demandesAgent, error: agentDemandesError } = await supabase
                    .from('demandes')
                    .select('*')
                    .eq('agent_assigné_id', agent.id)
                    .eq('statut', 'valide');

                if (!agentDemandesError && demandesAgent && demandesAgent.length > 0) {
                    stats.agents_performance.push({
                        id: agent.id,
                        nom: agent.nom,
                        demandes_validees: demandesAgent.length
                    });
                }
            }

            // Trier par performance décroissante
            stats.agents_performance.sort((a, b) => b.demandes_validees - a.demandes_validees);
            stats.agents_performance = stats.agents_performance.slice(0, 10);

        } catch (e) {
            stats.utilisateurs_actifs = 0;
            stats.agents_actifs = 0;
            stats.agents_performance = [];
        }

        // Statistiques signalements
        try {
            const { data: signalementsEnAttente, error: signalementsError } = await supabase
                .from('signalements')
                .select('*')
                .eq('statut', 'en_attente');

            if (signalementsError) throw signalementsError;

            stats.signalements_en_attente = signalementsEnAttente.length;

        } catch (e) {
            stats.signalements_en_attente = 0;
        }

        // Statistiques emplacements marché
        try {
            const { data: emplacementsActifs, error: emplacementsActifsError } = await supabase
                .from('emplacements_marche')
                .select('*')
                .eq('statut', 'actif');

            if (emplacementsActifsError) throw emplacementsActifsError;

            stats.emplacements_occupes = emplacementsActifs.length;

            // Total emplacements
            const { data: allEmplacements, error: allEmplacementsError } = await supabase
                .from('emplacements_marche')
                .select('*');

            if (allEmplacementsError) throw allEmplacementsError;

            const totalEmplacements = allEmplacements.length;
            stats.taux_occupation = totalEmplacements > 0 
                ? Math.round((emplacementsActifs.length / totalEmplacements) * 100 * 10) / 10 
                : 0;

            // Recettes marchés (basées sur les paiements liés aux emplacements)
            stats.recettes_marches = 0; // TODO: calculer si nécessaire

        } catch (e) {
            stats.emplacements_occupes = 0;
            stats.taux_occupation = 0;
            stats.recettes_marches = 0;
        }

        return sendJSONResponse(200, {
            success: true,
            data: stats,
            message: 'Statistiques récupérées'
        });

    } catch (error) {
        console.error('Erreur récupération stats:', error);
        
        // Retourner des stats vides plutôt qu'une erreur
        return sendJSONResponse(200, {
            success: true,
            data: {
                demandes_mois: 0,
                demandes_validees: 0,
                delai_moyen: 0,
                taux_satisfaction: 0,
                recettes_mois: 0,
                utilisateurs_actifs: 0,
                agents_actifs: 0,
                agents_performance: [],
                signalements_en_attente: 0,
                emplacements_occupes: 0,
                taux_occupation: 0,
                recettes_marches: 0
            },
            message: 'Statistiques récupérées'
        });
    }
};

