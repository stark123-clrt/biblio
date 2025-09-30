<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

//  LOGGING DÉTAILLÉ

function logTTS($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [TTS-{$level}] {$message}" . PHP_EOL;
    error_log($logMessage);
    

}

try {
    logTTS("=== DÉBUT REQUÊTE TTS ===");
    
    //  RÉCUPÉRATION DES PARAMÈTRES
    $text = isset($_POST['text']) ? trim($_POST['text']) : '';
    $chunk_id = isset($_POST['chunk_id']) ? $_POST['chunk_id'] : uniqid('chunk_', true);
    $speed = isset($_POST['speed']) ? floatval($_POST['speed']) : 1.0;
    
    logTTS("Paramètres reçus - Text length: " . strlen($text) . ", Chunk ID: {$chunk_id}, Speed: {$speed}");
    
    //  VALIDATION DES ENTRÉES

    if (empty($text)) {
        logTTS("ERREUR: Texte vide reçu", 'ERROR');
        echo json_encode([
            'success' => false,
            'error' => 'Texte requis pour la synthèse vocale',
            'debug_info' => [
                'post_data' => $_POST,
                'text_length' => strlen($text)
            ]
        ]);
        exit();
    }
    
    if (strlen($text) > 2000) {
        logTTS("ATTENTION: Texte très long (" . strlen($text) . " caractères)", 'WARN');
        $text = substr($text, 0, 2000);
    }
    
    //  CONFIGURATION DES CHEMINS
    $audioDir = __DIR__ . '/temp/audio/';
    $tempDir = __DIR__ . '/temp/';
    
    // Créer les dossiers si nécessaire
    foreach ([$audioDir, $tempDir] as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Impossible de créer le dossier: {$dir}");
            }
            logTTS("Dossier créé: {$dir}");
        }
    }
    
    // GÉNÉRATION DU NOM DE FICHIER SÉCURISÉ
    $safeChunkId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $chunk_id);
    $outputFile = $safeChunkId . '.wav';
    $fullPath = $audioDir . $outputFile;
    
    // Chemin pour WSL (adapter selon votre configuration)
    $wslAudioDir = '/mnt/c/xampp/htdocs/biblio/temp/audio/';
    $wslPath = $wslAudioDir . $outputFile;
    
    logTTS("Fichier de sortie: {$fullPath}");
    
    //  VÉRIFICATION DU CACHE
    if (file_exists($fullPath)) {
        $fileSize = filesize($fullPath);
        $fileAge = time() - filemtime($fullPath);
        
        // Considérer le fichier valide s'il fait plus de 1KB et moins de 24h

        if ($fileSize > 1000 && $fileAge < 86400) {
            logTTS("Cache HIT - Fichier existant utilisé ({$fileSize} bytes, {$fileAge}s)");
            
            echo json_encode([
                'success' => true,
                'audio_url' => '/biblio/temp/audio/' . $outputFile,
                'chunk_id' => $chunk_id,
                'cached' => true,
                'file_size' => $fileSize,
                'voice_engine' => 'Piper TTS (Cached)',
                'processing_time' => '0ms'
            ]);
            exit();
        } else {
            // Supprimer fichier invalide
            unlink($fullPath);
            logTTS("Cache MISS - Fichier invalide supprimé (taille: {$fileSize})");
        }
    }
    
    //  NETTOYAGE DU TEXTE POUR PIPER
    $cleanText = $text;
    
    // Remplacer caractères problématiques
    
    // Normaliser les espaces
    $cleanText = preg_replace('/\s+/', ' ', $cleanText);
    $cleanText = trim($cleanText);
    
    if (empty($cleanText)) {
        throw new Exception("Texte vide après nettoyage");
    }
    
    logTTS("Texte nettoyé - longueur finale: " . strlen($cleanText));
    
    //  CRÉATION DU FICHIER TEMPORAIRE POUR LE TEXTE
    $tempTextFile = tempnam($tempDir, 'piper_text_');
    if (!$tempTextFile) {
        throw new Exception("Impossible de créer le fichier temporaire");
    }
    
    $bytesWritten = file_put_contents($tempTextFile, $cleanText);
    if ($bytesWritten === false) {
        throw new Exception("Impossible d'écrire dans le fichier temporaire");
    }
    
    // Convertir le chemin pour WSL

    $wslTempFile = str_replace('\\', '/', str_replace('C:', '/mnt/c', $tempTextFile));
    
    logTTS("Fichier temporaire créé: {$tempTextFile} ({$bytesWritten} bytes)");
    
    // CONFIGURATION PIPER
    // $voiceModel = 'fr_FR-siwis-medium.onnx';

    $voiceModel = 'fr-FR-YvesNeural';

    //$voiceModel = 'fr_FR-siwis-low.onnx';  plus rapide moins memoire
    
    // Paramètres optimisés pour la qualité et la vitesse
    
    $length_scale = 2.0;   // ← Vitesse normale
    $noise_scale = 0.5;    // ← Plus d'expression (au lieu de 0.3)
    $noise_w = 0.9;
    

    // CONSTRUCTION DE LA COMMANDE PIPER
    $piperCommand = sprintf(
        'wsl bash -c "cd /mnt/c/Users/chris/edge && source edge_env/bin/activate && timeout 30s cat %s | python edge_piper.py --model %s --length_scale %.2f --noise_scale %.2f --noise_w %.2f --output_file %s 2>&1"',
        escapeshellarg($wslTempFile),
        escapeshellarg($voiceModel),
        $length_scale,
        $noise_scale,
        $noise_w,
        escapeshellarg($wslPath)
    );
    

    logTTS("Commande Piper: {$piperCommand}");
    
    // EXÉCUTION AVEC TIMEOUT
    $startTime = microtime(true);
    
    $output = shell_exec($piperCommand);
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    
    logTTS("Exécution terminée en {$executionTime}ms");
    logTTS("Sortie Piper: " . trim($output));
    
    // NETTOYAGE DU FICHIER TEMPORAIRE

    if (file_exists($tempTextFile)) {
        unlink($tempTextFile);
    }
    
    //  ATTENTE ET VÉRIFICATION DU FICHIER
    $maxWaitTime = 20; 
    $waitCount = 0;
    $checkInterval = 0.5; 
    
    logTTS("Attente de la génération du fichier audio...");
    
    while ($waitCount < $maxWaitTime) {
        if (file_exists($fullPath)) {
            $currentSize = filesize($fullPath);
            
            if ($currentSize > 1000) {
               
                usleep(500000);
                
                $finalSize = filesize($fullPath);
                
               
                if ($finalSize == $currentSize && $finalSize > 1000) {
                    logTTS("Fichier audio généré avec succès ({$finalSize} bytes après {$waitCount}s)");
                    break;
                }
            }
        }
        
        usleep($checkInterval * 1000000); 
        $waitCount += $checkInterval;
    }
    

    if (!file_exists($fullPath)) {
        throw new Exception("Fichier audio non généré - Timeout après {$maxWaitTime}s");
    }
    
    $finalFileSize = filesize($fullPath);
    
    if ($finalFileSize < 1000) {
        throw new Exception("Fichier audio trop petit ({$finalFileSize} bytes) - Génération échouée");
    }
    

    $response = [
        'success' => true,
        'audio_url' => '/biblio/temp/audio/' . $outputFile,
        'chunk_id' => $chunk_id,
        'cached' => false,
        'file_size' => $finalFileSize,
        'text_length' => strlen($text),
        'voice_model' => $voiceModel,
        // 'voice_engine' => 'Piper TTS',
        'voice_engine' => 'Edge TTS (Microsoft)',
        'processing_time' => $executionTime . 'ms',
        'wait_time' => round($waitCount, 1) . 's',
        'parameters' => [
            'length_scale' => $length_scale,
            'noise_scale' => $noise_scale,
            'noise_w' => $noise_w,
            'speed' => $speed
        ],
        'debug_info' => [
            'piper_output' => trim($output),
            'temp_file_created' => true,
            'wsl_path' => $wslPath
        ]
    ];
    
    logTTS("SUCCÈS - Audio généré: {$finalFileSize} bytes en {$executionTime}ms");
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {

    $errorMessage = $e->getMessage();
    $errorDetails = [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    logTTS("EXCEPTION: {$errorMessage}", 'ERROR');
    logTTS("Détails: " . print_r($errorDetails, true), 'ERROR');
    
   
    if (isset($tempTextFile) && file_exists($tempTextFile)) {
        unlink($tempTextFile);
    }
    
    if (isset($fullPath) && file_exists($fullPath) && filesize($fullPath) < 1000) {
        unlink($fullPath); 
    }
    
    $errorResponse = [
        'success' => false,
        'error' => $errorMessage,
        'error_type' => get_class($e),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'text_preview' => isset($text) ? substr($text, 0, 100) . '...' : 'N/A',
            'text_length' => isset($text) ? strlen($text) : 0,
            'chunk_id' => $chunk_id ?? 'N/A',
            'file_path' => $fullPath ?? 'N/A',
            'piper_output' => isset($output) ? trim($output) : 'N/A',
            'execution_time' => isset($executionTime) ? $executionTime . 'ms' : 'N/A'
        ]
    ];
    

    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        unset($errorResponse['debug_info']['piper_output']);
        unset($errorResponse['error_type']);
    }
    
    http_response_code(500);
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
    
} catch (Error $e) {
  
    logTTS("ERREUR FATALE PHP: " . $e->getMessage(), 'FATAL');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur système fatale',
        'error_code' => 'FATAL_ERROR',
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Une erreur système s\'est produite. Veuillez réessayer.'
    ]);
}

logTTS("=== FIN REQUÊTE TTS ===");
?>

