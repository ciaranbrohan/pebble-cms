<?php

require_once __DIR__ . '/YamlParser.php';

class Content {
    private $frontmatter;
    private $content;
    private $raw;
    private $isModular = false;
    private $modules = [];
    private $filePath;

    public function __construct($file) {
        $this->filePath = $file;
        $this->raw = file_get_contents($file);
        $this->parse();
        
        // Check if this is a modular page
        if ($this->get('template') === 'modular') {
            $this->isModular = true;
            $this->loadModules(dirname($file));
        }
    }

    private function parse() {
        // Split frontmatter and content
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $this->raw, $matches)) {
            $this->frontmatter = YamlParser::parse($matches[1]);
            $this->content = $matches[2];
        } else {
            $this->frontmatter = [];
            $this->content = $this->raw;
        }
    }

    private function loadModules($dirPath) {
        // Get all directories that start with underscore
        $dirs = glob($dirPath . '/_*', GLOB_ONLYDIR);
        
        // Store modules with their order
        $modulesWithOrder = [];
        
        foreach ($dirs as $dir) {
            $moduleFile = $dir . '/default.md';
            if (file_exists($moduleFile)) {
                $module = new Content($moduleFile);
                $order = $module->get('order', 9999);
                $modulesWithOrder[] = [
                    'order' => $order,
                    'content' => $module,
                    'name' => basename($dir)
                ];
            }
        }
        
        // Sort modules by order
        usort($modulesWithOrder, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        $this->modules = array_map(function($module) {
            return $module['content'];
        }, $modulesWithOrder);
    }

    public function isModular() {
        return $this->isModular;
    }

    public function getModules() {
        return $this->modules;
    }

    public function getFilePath() {
        return $this->filePath;
    }

    public function getFrontmatter() {
        return $this->frontmatter;
    }

    public function getContent() {
        return $this->content;
    }

    public function get($key, $default = null) {
        return isset($this->frontmatter[$key]) ? $this->frontmatter[$key] : $default;
    }
} 