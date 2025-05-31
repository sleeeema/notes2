<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['input_text'])) {
    $input_text = $_POST['input_text'];
    
    // Split text into sentences
    $sentences = preg_split('/(?<=[.!?])\s+/', $input_text, -1, PREG_SPLIT_NO_EMPTY);
    
    // Calculate importance of each sentence based on word frequency
    $word_frequency = [];
    $sentence_scores = [];
    
    foreach ($sentences as $sentence) {
        // Get words and clean them
        $words = str_word_count(strtolower($sentence), 1, 'àáâãäçèéêëìíîïñòóôõöùúûüýÿ');
        
        // Count word frequency
        foreach ($words as $word) {
            if (strlen($word) > 3) { // Skip short words
                $word_frequency[$word] = isset($word_frequency[$word]) ? 
                    $word_frequency[$word] + 1 : 1;
            }
        }
    }
    
    // Score each sentence based on word frequency
    foreach ($sentences as $idx => $sentence) {
        $words = str_word_count(strtolower($sentence), 1, 'àáâãäçèéêëìíîïñòóôõöùúûüýÿ');
        $score = 0;
        
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $score += $word_frequency[$word];
            }
        }
        
        $sentence_scores[$idx] = $score / count($words);
    }
    
    // Sort sentences by score
    arsort($sentence_scores);
    
    // Take top 30% of sentences or at least 2 sentences
    $num_sentences = max(2, ceil(count($sentences) * 0.3));
    $top_sentences = array_slice($sentence_scores, 0, $num_sentences, true);
    ksort($top_sentences); // Restore original order
    
    // Build summary
    $summary = [];
    foreach ($top_sentences as $idx => $score) {
        $summary[] = trim($sentences[$idx]);
    }
    
    $summary_text = implode("\n\n", $summary);
    
    // Add introduction
    $summary_text = "Points clés :\n\n" . $summary_text;
    
    echo json_encode(['summary' => $summary_text]);
} else {
    echo json_encode(['error' => 'Aucun texte fourni.']);
}
?>
