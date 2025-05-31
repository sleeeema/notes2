<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['notes'])) {
    $notes = $_POST['notes'];
    
    // Split text into sentences
    $sentences = preg_split('/(?<=[.!?])\s+/', $notes, -1, PREG_SPLIT_NO_EMPTY);
    
    // Remove very short sentences
    $sentences = array_filter($sentences, function($sentence) {
        return str_word_count($sentence) > 5;
    });
    
    // Shuffle sentences and take 5 for questions
    shuffle($sentences);
    $question_sentences = array_slice($sentences, 0, min(5, count($sentences)));
    
    $quiz = [];
    $answers = [];
    foreach ($question_sentences as $index => $sentence) {
        // Get key terms from the sentence
        $words = str_word_count(strtolower($sentence), 1, 'àáâãäçèéêëìíîïñòóôõöùúûüýÿ');
        $words = array_filter($words, function($word) {
            return strlen($word) > 3;
        });
        
        // Get a random key term to create a question
        $key_term = array_rand(array_flip($words));
        
        // Create the question
        $question = str_replace($key_term, "______", $sentence);
        $question = "Question " . ($index + 1) . ": Complétez la phrase suivante :\n" . $question . "\n\n";
        
        // Generate distractors (wrong answers)
        $distractors = [];
        foreach ($sentences as $other_sentence) {
            $other_words = str_word_count(strtolower($other_sentence), 1, 'àáâãäçèéêëìíîïñòóôõöùúûüýÿ');
            $other_words = array_filter($other_words, function($word) use ($key_term) {
                return strlen($word) > 3 && $word !== $key_term;
            });
            $distractors = array_merge($distractors, $other_words);
        }
        
        // Remove duplicates and get 3 random distractors
        $distractors = array_unique($distractors);
        shuffle($distractors);
        $distractors = array_slice($distractors, 0, 3);
        
        // Create options array with correct answer and distractors
        $options = array_merge([$key_term], $distractors);
        shuffle($options);
        
        // Find the letter of the correct answer
        $correct_letter = '';
        $letters = ['A', 'B', 'C', 'D'];
        foreach ($options as $i => $option) {
            if ($option === $key_term) {
                $correct_letter = $letters[$i];
                break;
            }
        }
        
        // Add options to question
        foreach ($options as $i => $option) {
            $question .= $letters[$i] . ") " . ucfirst($option) . "\n";
        }
        
        // Add the correct answer
        $answers[] = "Question " . ($index + 1) . " - Réponse correcte: " . $correct_letter . ") " . ucfirst($key_term);
        
        $quiz[] = $question;
    }
    
    $quiz_text = implode("\n", $quiz);
    $answers_text = "\n\nRéponses correctes:\n" . implode("\n", $answers);
    
    // Add introduction and answers
    $quiz_text = "Quiz basé sur vos notes :\n\n" . $quiz_text . $answers_text;
    
    echo json_encode(['quiz' => $quiz_text]);
} else {
    echo json_encode(['error' => 'Aucunes notes fournies.']);
} 