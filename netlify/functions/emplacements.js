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
            'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization',
            'Access-Control-Allow-Credentials': 'true'
        },
        body: JSON.stringify(body)
    };
}

// Helper to enrich with user names
async function enrichWithUserNames(items, userIdField, role = null) {
    if (!items || items.length === 0) return items;
    
    const userIds = [...new Set(items.map(item => item[userIdField]).filter(id => id))];
    if (userIds.length === 0) return items;
    
    try {
        const { data: users, error } = await supabase
            .from('utilisateurs')
            .select('id, nom')
            .in('id', userIds);
        
        if (error) throw error;
        
        const usersMap = {};
        if (users) {
            users.forEach(user => {
                usersMap[user.id] = user.nom;
            });
        }
        
        return items.map(item => ({
            ...item,
            utilisateur_nom: usersMap[item[userIdField]] || 'N/A'
        }));
    } catch (error) {
        console.error('Erreur enrichWithUserNames:', error);
        return items;
    }
}

exports.handler = async (event) => {
    // Handle CORS preflight
    if (event.httpMethod === 'OPTIONS') {
        return sendJSONResponse(200, {});
    }

    try {
        // Extract user info from query parameters or body
        let userId = null;
        let userRole = null;
        
        if (event.queryStringParameters) {
            userId = event.queryStringParameters._user_id || null;
            userRole = event.queryStringParameters._user_role || null;
        }
        
        if (event.body && event.httpMethod === 'POST') {
            try {
                const body = JSON.parse(event.body);
                userId = body._user_id || userId;
                userRole = body._user_role || userRole;
            } catch (e) {
                // Ignore parsing errors
            }
        }

        if (!userId) {
            return sendJSONResponse(401, {
                success: false,
                message: 'Non authentifié'
            });
        }

        if (event.httpMethod === 'GET') {
            let query = supabase
                .from('emplacements_marche')
                .select('*')
                .order('date_attribution', { ascending: false });
            
            // Filter by user if commerçant
            if (userRole === 'commercant') {
                query = query.eq('utilisateur_id', userId);
            }
            
            const { data: emplacements, error } = await query;
            
            if (error) throw error;
            
            // Enrich with user names if not commerçant
            let enrichedEmplacements = emplacements || [];
            if (userRole !== 'commercant') {
                enrichedEmplacements = await enrichWithUserNames(enrichedEmplacements, 'utilisateur_id');
            }
            
            return sendJSONResponse(200, {
                success: true,
                data: enrichedEmplacements,
                message: 'Emplacements récupérés'
            });
            
        } else if (event.httpMethod === 'POST') {
            if (userRole !== 'commercant') {
                return sendJSONResponse(403, {
                    success: false,
                    message: 'Non autorisé'
                });
            }
            
            const body = JSON.parse(event.body);
            const { marche, numero_stand, type_stand, statut, date_attribution } = body;
            
            if (!marche || !numero_stand) {
                return sendJSONResponse(400, {
                    success: false,
                    message: 'Champs manquants: marche, numero_stand'
                });
            }
            
            const emplacementData = {
                utilisateur_id: parseInt(userId),
                marche: marche,
                numero_stand: numero_stand,
                type_stand: type_stand || null,
                statut: statut || 'actif',
                date_attribution: date_attribution || new Date().toISOString().split('T')[0]
            };
            
            const { data, error } = await supabase
                .from('emplacements_marche')
                .insert([emplacementData])
                .select();
            
            if (error) throw error;
            
            return sendJSONResponse(200, {
                success: true,
                data: data && data.length > 0 ? data[0] : emplacementData,
                message: 'Emplacement réservé avec succès'
            });
            
        } else {
            return sendJSONResponse(405, {
                success: false,
                message: 'Méthode non autorisée'
            });
        }
        
    } catch (error) {
        console.error('Erreur emplacements:', error);
        
        if (event.httpMethod === 'GET') {
            // Return empty array for GET errors
            return sendJSONResponse(200, {
                success: true,
                data: [],
                message: 'Aucun emplacement disponible'
            });
        }
        
        return sendJSONResponse(500, {
            success: false,
            message: 'Erreur serveur: ' + error.message
        });
    }
};

