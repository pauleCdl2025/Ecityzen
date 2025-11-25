// Netlify Function pour le géocodage inverse
const fetch = require('node-fetch');

exports.handler = async (event, context) => {
  if (event.httpMethod === 'OPTIONS') {
    return {
      statusCode: 200,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type'
      },
      body: ''
    };
  }

  if (event.httpMethod !== 'GET') {
    return {
      statusCode: 405,
      headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
      body: JSON.stringify({ success: false, message: 'Méthode non autorisée' })
    };
  }

  const lat = event.queryStringParameters?.lat ? parseFloat(event.queryStringParameters.lat) : null;
  const lng = event.queryStringParameters?.lng ? parseFloat(event.queryStringParameters.lng) : null;
  
  if (!lat || !lng) {
    return {
      statusCode: 400,
      headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
      body: JSON.stringify({ success: false, message: 'Coordonnées GPS requises' })
    };
  }
  
  try {
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
    
    const response = await fetch(url, {
      headers: {
        'User-Agent': 'e-cityzen-gabon/1.0'
      }
    });
    
    if (!response.ok) {
      throw new Error('Erreur Nominatim');
    }
    
    const data = await response.json();
    
    if (data && data.address) {
      const address = data.address;
      
      const result = {
        success: true,
        data: {
          display_name: data.display_name || '',
          quartier: address.suburb || address.neighbourhood || address.quarter || '',
          ville: address.city || address.town || address.municipality || 'Libreville',
          arrondissement: address.city_district || address.district || '',
          province: address.state || address.region || 'Estuaire',
          pays: address.country || 'Gabon',
          code_postal: address.postcode || '',
          rue: address.road || address.street || '',
          numero: address.house_number || '',
          coordonnees: {
            lat: lat,
            lng: lng
          }
        }
      };
      
      // Construire l'adresse complète
      const adresseComplete = [];
      if (result.data.rue) {
        if (result.data.numero) {
          adresseComplete.push(result.data.numero + ' ' + result.data.rue);
        } else {
          adresseComplete.push(result.data.rue);
        }
      }
      if (result.data.quartier) {
        adresseComplete.push('Quartier ' + result.data.quartier);
      }
      if (result.data.arrondissement) {
        adresseComplete.push(result.data.arrondissement);
      }
      if (result.data.ville) {
        adresseComplete.push(result.data.ville);
      }
      if (result.data.province) {
        adresseComplete.push(result.data.province);
      }
      
      result.data.adresse_complete = adresseComplete.length > 0 
        ? adresseComplete.join(', ') 
        : result.data.display_name;
      
      return {
        statusCode: 200,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify(result)
      };
    } else {
      return {
        statusCode: 404,
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({ success: false, message: 'Adresse non trouvée' })
      };
    }
  } catch (error) {
    console.error('Erreur géocodage:', error);
    return {
      statusCode: 500,
      headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
      body: JSON.stringify({ success: false, message: 'Erreur lors de la récupération de l\'adresse' })
    };
  }
};

