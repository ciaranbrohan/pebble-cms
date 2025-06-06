<?php

class YamlParser {
    public static function parse($content) {
        $data = [];
        
        // Split the content into lines
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse key-value pairs
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $pair)) {
                $key = trim($pair[1]);
                $value = trim($pair[2]);
                
                // Handle quoted strings
                if (preg_match('/^["\'](.*)["\']$/', $value, $quoted)) {
                    $value = $quoted[1];
                }
                
                // Handle boolean values
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                
                // Handle numeric values
                if (is_numeric($value)) {
                    $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                }
                
                // Handle empty values
                if ($value === '') {
                    $value = null;
                }
                
                $data[$key] = $value;
            }
        }
        
        return $data;
    }

    public static function dump($data) {
        $output = '';
        
        foreach ($data as $key => $value) {
            // Handle different value types
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                // Quote strings that contain special characters
                if (strpos($value, ':') !== false || strpos($value, '#') !== false || 
                    strpos($value, '[') !== false || strpos($value, ']') !== false ||
                    strpos($value, '{') !== false || strpos($value, '}') !== false ||
                    strpos($value, ',') !== false || strpos($value, '&') !== false ||
                    strpos($value, '*') !== false || strpos($value, '!') !== false ||
                    strpos($value, '|') !== false || strpos($value, '>') !== false ||
                    strpos($value, "'") !== false || strpos($value, '"') !== false ||
                    strpos($value, '%') !== false || strpos($value, '@') !== false ||
                    strpos($value, '`') !== false || strpos($value, ' ') !== false) {
                    $value = '"' . str_replace('"', '\\"', $value) . '"';
                }
            }
            
            $output .= $key . ': ' . $value . "\n";
        }
        
        return $output;
    }
} 