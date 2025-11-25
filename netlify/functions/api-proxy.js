// Proxy Netlify Function pour les APIs PHP
// Note: Cette fonction proxy les appels vers votre serveur PHP
// Vous devez configurer NETLIFY_API_BASE_URL dans les variables d'environnement Netlify

exports.handler = async (event, context) => {
  const path = event.path.replace('/.netlify/functions/api-proxy', '');
  const apiBaseUrl = process.env.NETLIFY_API_BASE_URL || 'http://localhost/Ecityzen';
  
  // Récupérer les paramètres de la requête
  const method = event.httpMethod;
  const headers = { ...event.headers };
  delete headers.host; // Retirer le host de Netlify
  
  // Construire l'URL de destination
  const url = `${apiBaseUrl}/api${path}${event.rawQuery ? `?${event.rawQuery}` : ''}`;
  
  try {
    // Faire l'appel vers le serveur PHP
    // Note: Node.js 18+ a fetch intégré, mais pour compatibilité on utilise node-fetch
    const fetch = require('node-fetch');
    const response = await fetch(url, {
      method: method,
      headers: headers,
      body: method !== 'GET' && method !== 'HEAD' ? event.body : undefined
    });
    
    const data = await response.text();
    
    return {
      statusCode: response.status,
      headers: {
        'Content-Type': response.headers.get('content-type') || 'application/json',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization'
      },
      body: data
    };
  } catch (error) {
    return {
      statusCode: 500,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*'
      },
      body: JSON.stringify({
        success: false,
        error: error.message
      })
    };
  }
};

