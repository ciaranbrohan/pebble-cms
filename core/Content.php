<?php

class Content {
    private $frontmatter;
    private $content;
    private $raw;

    public function __construct($file) {
        $this->raw = file_get_contents($file);
        $this->parse();
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