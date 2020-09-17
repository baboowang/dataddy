<?php
namespace MY;

class Plugin_Info {
    private $file = NULL;
    private $content = NULL;
    public $plugin_name = NULL;
    public $name = NULL;
    public $desc = NULL;
    public $author = NULL;
    public $segments = [];

    public function __construct($file)
    {
        $this->file = $file;
        if (!is_file($file)) {
            throw \Exception("Plugin file not exists. $file");
        }

        $fileinfo = pathinfo($file);
        $this->plugin_name = basename($fileinfo['dirname']) . '\\' . $fileinfo['filename'];
        $this->parse();
    }

    public function getContent()
    {
        if (is_null($this->content)) {
            $this->content = file_get_contents($this->file);
        }

        return $this->content;
    }

    private function parse()
    {
        $content = $this->getContent();

        if (!preg_match('@/\*(.+)\*/@usmi', $content, $ma)) {
            return FALSE;
        }
        $lines = preg_split('@\n@', $ma[1]);
        $segments = [];
        $segment_name = NULL;
        foreach ($lines as $line) {
            $line = preg_replace('@\s+$@', '', preg_replace('@^\s*\*@', '', $line));
            if (!$line) {
                continue;
            }
            if (preg_match('@\@(\w+)\s+(.+)@', $line, $ma)) {
                $segment_name = $ma[1];
                $segments[$segment_name] = $ma[2];
                continue;
            }
            if ($segment_name) {
                $segments[$segment_name] .= "\n" . $line;
            }
        }
        $this->segments = $segments;
        $this->name = $segments['name'] ?? '';
        $this->desc = $segments['desc'] ?? '';
        $this->author = $segments['author'] ?? '';
    }
}
