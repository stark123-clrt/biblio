<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Test XTTS avec FEMALE.wav...\n";

//AVEC FEMALE.WAV qui marche bien
$post_data = json_encode([
    'data' => [
        'Bonjour, ceci est un test avec la voix féminine',        // Texte français
        'fr',                                                     // Français
        ['path' => 'http://127.0.0.1:7860/file=C:\\pinokio\\api\\xtts.pinokio.git\\cache\\female.wav'], // ← FEMALE.wav !
        true                                                      // Accord
    ]
]);

echo "POST data (female): " . $post_data . "\n";


$post_context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $post_data,
        'timeout' => 60
    ]
]);



$result = file_get_contents('http://127.0.0.1:7860/call/predict', false, $post_context);


if ($result) {
    $response = json_decode($result, true);
    if (isset($response['event_id'])) {
        $event_id = $response['event_id'];
        echo "EVENT_ID: " . $event_id . "\n";
        
        echo "Génération avec voix féminine...\n";
        sleep(25); 
        
        $get_url = 'http://127.0.0.1:7860/call/predict/' . $event_id;
        $get_result = file_get_contents($get_url);
        
        echo "Résultat:\n";
        echo $get_result . "\n";
        

        // Vérifier succès
        if (strpos($get_result, 'event: complete') !== false) {
            echo "SUCCESS avec voix féminine !\n";
        }
    }
}



?>

