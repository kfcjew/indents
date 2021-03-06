<?php
namespace Maximizer\Indentations;

/**
* Parser of indented strings.
* You may use this class in your application logic.
*/
class Indents
{

    const TO_ASSOC  = 1;
    const TO_OBJECT = 0;
    
    private $stack = [];
    private $path  = NULL;
    private $array = NULL;
    
    /**
    * Sets and formats the input.
    * @param string $string - input as string.
    */
    private function setInput(string $string): void
    {
        $string      = str_replace("\r\n", "\n", str_replace("    ", "\t", $string));
        $this->input = preg_replace("/[ ]?(Rem|%)(.++)$/m", "", $string);
    }
    
    /**
    * Counts indent level
    * @param string $string - string
    * @returns indent level
    */
    private function countIndentLevel(string $string): int
    {
        $level = 0;
        foreach(str_split($string) as $char) {
            if($char !== "\t") break;
            $level++;
        }
        
        return $level;
    }
    
    /**
    * @returns pointer to the current node in stack.
    */
    private function &getByPath(): array
    {
        $array = &$this->stack;
        foreach($this->path as $index) {
            $array = &$array[$index];
        }
        return $array;
    }
    
    /**
    * Generates branch from current node in stack.
    * Modifies the path to be pointing to the newly generated node.
    * @returns pointer to the new node in stack.
    */
    private function &forkByPath(): array
    {
        $parent           = &$this->getByPath();
        $current          = end($parent);
        $parent           = @array_diff($parent, [$current]);
        $parent[$current] = [];
        $this->path[]     = $current;
        return $parent[$current];
    }
    
    /**
    * @param string $element - element
    * @returns value of element with correct type
    */
    private function elval(string $element)
    {
        if(is_numeric($element)) return $element + 0;
        if(substr($element, 0, 2) === "0x" && extension_loaded("ctype")) {
            $xdigit = substr($element, 2);
            if(ctype_xdigit($xdigit)) return eval("return $element;") + 0;
        }
        
        return $element;
    }
    
    /**
    * Determines the strategy, then pushes element to its coresponding place in stack.
    * @param   string  $element - the element, should be string, the conversion to other types is being done automatically.
    * @param   integer $indents - the number of indents before the element in document.
    * @throws  IndentParseException
    * @returns void
    */
    private function push_to_stack(string $element, int $indents): void
    {
        if($element === "\n" || ord($element) === 0) return; #Ignore meaningless characters

        $element = $this->elval($element);
        if($indents === 0 && is_null($this->path)) {
            $this->stack[$element] = [];
            $this->path            = [$element];
        } else if(!is_null($this->path)) {
            $path_size = sizeof($this->path);
            if($indents - 1 > $path_size || $indents < 1) {
                $text = "Unexpected T_INDENT near `$element`. Was the indentation too deep ($indents)?";
                throw new IndentParseException($text);
            }

            switch ($indents <=> $path_size) {
                case -1:
                    $this->path = array_slice($this->path, 0, -1 * ($path_size - $indents));
                    $parent     = &$this->getByPath();
                    break;
                case 0:
                    $parent = &$this->getByPath();
                    break;
                case 1:
                    $parent = &$this->forkByPath();
                    break;
            }

            $parent[] = $element;
        } else {
            $text = "Invalid indentation depth near `$element` ($indents indents)";
            throw new IndentParseException($text);
        }
    }
    
    /**
    * Parses tree.
    * @param   integer $as - mode code (example, IndentParser::TO_OBJECT)
    * @returns stack depending on selected mode.
    */
    private function parse_tree(int $as)
    {
        $list = explode("\n", $this->input);
        foreach($list as $row) {
            $indents = $this->countIndentLevel($row);
            $this->push_to_stack(preg_replace("/[\\t]+/", "", $row), $indents);
        }
        return $as ? $this->stack : (object) $this->stack;
    }
    
    /**
    * Transforms indented string to array/object from string.
    * @param string  $string - indented string document.
    * @param integer $mode   - conversion mode (example, IndentParser::TO_OBJECT)
    * @see   IndentParser->parseFromFile
    * @returns stack depending on selected mode.
    */
    function parseFromString(string $string, int $mode = 0)
    {
        $this->setInput($string);
        return $this->parse_tree($mode);
    }
    
    /**
    * Transforms indented string to array/object from file.
    * @param string  $file - filename of file, which contains indented string document.
    * @param integer $mode - conversion mode (example, IndentParser::TO_OBJECT)
    * @see   IndentParser->parseFromString
    * @returns stack depending on selected mode.
    */
    function parseFromFile(string $file, int $mode = 0)
    {
        $this->setInput(file_get_contents($file));
        return $this->parse_tree($mode);
    }
}
