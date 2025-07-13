<?php
// kokoro_tts.php - API PHP → Python Kokoro
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
$PYTHON_SERVER = 'http://localhost:5000';

function callKokoro($endpoint, $data = null, $expectAudio = false) {
    global $PYTHON_SERVER;
    
    $url = $PYTHON_SERVER . $endpoint;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'Erreur réseau: ' . $error];
    }
    
    return [
        'success' => $httpCode === 200,
        'data' => $response,
        'code' => $httpCode,
        'content_type' => $contentType,
        'is_audio' => strpos($contentType, 'audio/') === 0
    ];
}

// Router simple
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'health':
        $result = callKokoro('/health');
        
        if ($result['success']) {
            echo $result['data'];
        } else {
            http_response_code(503);
            echo json_encode([
                'error' => 'Serveur Kokoro indisponible',
                'details' => $result['error'] ?? 'Erreur inconnue'
            ]);
        }
        break;
        
    case 'voices':
        $result = callKokoro('/voices');
        
        if ($result['success']) {
            echo $result['data'];
        } else {
            http_response_code(503);
            echo json_encode(['error' => 'Impossible de récupérer les voix']);
        }
        break;
        
    case 'synthesize':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode POST requise']);
            exit();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['text']) || empty(trim($input['text']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Texte requis']);
            exit();
        }
        
        // Paramètres par défaut
        $synthesisData = [
            'text' => trim($input['text']),
            'voice' => $input['voice'] ?? 'ff_siwis',
            'speed' => floatval($input['speed'] ?? 1.0)
        ];
        
        // Limitation sécurité
        if (strlen($synthesisData['text']) > 5000) {
            http_response_code(400);
            echo json_encode(['error' => 'Texte trop long (max 5000 caractères)']);
            exit();
        }
        
        $result = callKokoro('/synthesize', $synthesisData, true);
        
        if ($result['success'] && $result['is_audio']) {
            // Retourner l'audio directement
            header('Content-Type: audio/wav');
            header('Content-Disposition: attachment; filename="kokoro_speech.wav"');
            header('Content-Length: ' . strlen($result['data']));
            echo $result['data'];
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Erreur génération audio',
                'code' => $result['code'],
                'details' => !$result['success'] ? 'Serveur Python inaccessible' : 'Réponse non-audio'
            ]);
        }
        break;
        
    case 'status':
        // Status détaillé pour debugging
        $health = callKokoro('/health');
        $voices = callKokoro('/voices');
        
        $status = [
            'server_accessible' => $health['success'],
            'kokoro_ready' => false,
            'voices_available' => $voices['success'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($health['success']) {
            $healthData = json_decode($health['data'], true);
            $status['kokoro_ready'] = $healthData['kokoro_ready'] ?? false;
            $status['python_version'] = $healthData['python_version'] ?? 'Inconnue';
        }
        
        echo json_encode($status, JSON_PRETTY_PRINT);
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Action non trouvée',
            'available_actions' => ['health', 'voices', 'synthesize', 'status']
        ]);
}
?>