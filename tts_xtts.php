<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $text = isset($_POST['text']) ? trim($_POST['text']) : '';
    $chunk_id = isset($_POST['chunk_id']) ? $_POST['chunk_id'] : uniqid('xtts_', true);
    
    if (empty($text)) {
        throw new Exception('Texte requis');
    }
    
    // 🎯 CONFIGURATION EXACTE SELON L'API
    $xtts_base_url = 'http://127.0.0.1:7860';
    
    // 🚀 ÉTAPE 1: POST avec les VRAIS paramètres
    $post_data = json_encode([
        'data' => [
            $text,    // prompt (string)
            'fr',     // language (string) - français !
            null,     // audio_file_path (any) - pas d'audio référence
            true      // agree (boolean) - accepter les conditions
        ]
    ]);
    
    $post_context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $post_data,
            'timeout' => 30
        ]
    ]);
    
    $post_result = file_get_contents(
        $xtts_base_url . '/call/predict', 
        false, 
        $post_context
    );
    
    if (!$post_result) {
        throw new Exception('Erreur POST XTTS');
    }
    
    $post_response = json_decode($post_result, true);
    if (!isset($post_response['event_id'])) {
        throw new Exception('Pas d\'event_id: ' . print_r($post_response, true));
    }
    
    $event_id = $post_response['event_id'];
    
    // 🚀 ÉTAPE 2: GET - Attendre les résultats
    $get_url = $xtts_base_url . '/call/predict/' . $event_id;
    
    $max_attempts = 90; // 90 secondes max (XTTS plus lent)
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        $get_result = file_get_contents($get_url);
        
        if ($get_result) {
            $lines = explode("\n", $get_result);
            foreach ($lines as $i => $line) {
                if (strpos($line, 'event: complete') !== false) {
                    // La ligne suivante contient les données
                    if (isset($lines[$i + 1]) && strpos($lines[$i + 1], 'data:') !== false) {
                        $json_data = substr($lines[$i + 1], 5);
                        $result_data = json_decode(trim($json_data), true);
                        
                        // L'audio est dans result_data[1] selon l'API
                        if (isset($result_data[1]) && isset($result_data[1]['url'])) {
                            $audio_url = $result_data[1]['url'];
                            
                            // Télécharger le fichier audio
                            $audio_content = file_get_contents($xtts_base_url . $audio_url);
                            
                            if ($audio_content) {
                                $audio_dir = __DIR__ . '/temp/audio/';
                                $file_name = $chunk_id . '.wav';
                                $local_path = $audio_dir . $file_name;
                                
                                file_put_contents($local_path, $audio_content);
                                
                                echo json_encode([
                                    'success' => true,
                                    'audio_url' => '/biblio/temp/audio/' . $file_name,
                                    'voice_engine' => 'XTTS Neural v2 (Pinokio)',
                                    'chunk_id' => $chunk_id,
                                    'processing_time' => ($attempt * 1000) . 'ms'
                                ]);
                                exit();
                            }
                        }
                    }
                }
            }
        }
        
        $attempt++;
        sleep(1); // Attendre 1 seconde
    }
    
    throw new Exception('Timeout - XTTS trop long à répondre');
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'voice_engine' => 'XTTS Neural (Error)'
    ]);
}
?>