<?php
    
    class BisRecord {
        protected $originalFile = "bis_xml_input.xml";
        protected $exportFile = "bis_xml_output.xml"; 

        const ELEMENTS = [
                'title',
                'relation',
                'creator',
                'contributor',
                'format',
                'date',
                'language',
                'description',
                'source',
            ];

        function __construct($originalFile=null, $exportFile=null) {
            if ($originalFile) {
                $this->originalFile = $originalFile;
            }

            if ($exportFile) {
                $this->exportFile = $exportFile;
            }
        }

        public function getOriginalFile() {
            return $this->originalFile;
        }

        public function setOriginalFile($originalFile) {
            $this->originalFile = $originalFile;
        }

        public function getExportFile() {
            return $this->exportFile;
        }

        public function setExportFile($exportFile) {
            $this->exportFile = $exportFile;
        }
    }

    abstract class BisElement {
        protected $rawData;
        protected $printMe;
        protected $formattedData;
        protected $tag;
        protected $tagAttributes;

        function __construct($rawData) {
            $this->rawData = $rawData;
            $this->printMe = true;
            $this->_formatData();
        }

        protected function _formatData() {

            // function defined by child classes
            $data = $this->formatData();

            $data = trim($data);
            
            $this->formattedData = $data;
        }

        protected function formatData() {
            return $this->rawData;
        }

        public function getName() {
            return substr(get_class($this), 3);
        }

        public function getPrintMe() {
            return $this->printMe;
        }

        protected function setPrintMe($printMe) {
            $this->printMe = $printMe;
        }

        public function getFormattedData() {
            return $this->formattedData;
        }

        public function getTag() {
            return $this->tag;
        }

        public function getTagAttributes() {
            return $this->tagAttributes;
        }

        protected function setTag($tag, $tagAttributes) {
            $this->tag = $tag;
            $this->tagAttributes = $tagAttributes;
        }

        public function hasTag() {
            return !empty($this->tag);
        }
    }

    class BisTitle extends BisElement {
    }

    class BisRelation extends BisElement {

        protected function formatData() {
            $data = $this->rawData;
            if (!preg_match('/http|www/', $data)) {
                $this->setPrintMe(false);
            } 

            $content = 
                'Description hiérarchisée dans le catalogue des archives et manuscrits Calames';

            $tag = 'a';
            $tagAttributes = [
                'href' => $data,
                'title' => 'Lien vers la notice Calames',
                'target' => '_blank',
            ];

            $this->setTag($tag, $tagAttributes);

            return $content;
        }

        public function getName() {
            return 'Source';
        }
    }

    class BisCreator extends BisElement {
        protected function formatData() {
            $data = $this->rawData;

            // remove what is inside squared brackets
            $regex = '/\[[^\[\]]+\]/';
            while (preg_match($regex, $data)) {
                $data = preg_replace($regex, '', $data);
            }

            $data = trim($data);
            
            return $data;
        }
    }


    class BisContributor extends BisElement {
        protected function formatData() {
            $data = $this->rawData;

            // remove squared brackets
            $regex = '/^([^\[]+)(?:\[([^\[\]]+)\])?/';
            $replacement = 'trim($1). $2';
            $data = preg_replace_callback($regex,
                function ($matches) {
                    $data = trim($matches[1]);

                    if (isset($matches[2])) {
                        $data .= '. ' . $matches[2];
                    } 
                    return $data;
                },
                $data);

            return $data;
        }
    }

    class BisFormat extends BisElement {
    }

    class BisDate extends BisElement {
        protected function formatData() {
            $data = $this->rawData;

            // keep only first 4 digits
            if (preg_match('/\//', $data)) {
                $regex = '/(\d{4})\d{4}?\/(\d{4})\d{4}?/';
                $replacement = '$1/$2';
                $data = preg_replace($regex, $replacement, $data);
            } else {
                $data = substr($data, 0, 4);
            }

            return $data;
        }
    }

    class BisLanguage extends BisElement {
    }

    class BisDescription extends BisElement {
    }

    class BisSource extends BisElement {
        protected function formatData() {
            $data = $this->rawData;

            // remove starting number, whitespace characters and hyphen
            $regex = '/\d+\s+-\s+(.+)/';
            $replacement = '$1';
            $data = preg_replace($regex, $replacement, $data);
            
            $prefix = 'Bibliothèque interuniversitaire de la Sorbonne, cote : ';

            return $prefix . $data;
        }
    }

    // main program
    $originalFile = isset($argv[1]) ? $argv[1] : "";
    $exportFile = isset($argv[2]) ? $argv[2] : "";
    
    $record = new BisRecord($originalFile, $exportFile);

    // 1. checking original file
    if (file_exists($record->getOriginalFile())) {
        $records = simplexml_load_file($record->getOriginalFile());
    } else {
        exit("Error while opening file $records");
    }

    // 2. output
	// 2.1 initial tags

    $root = new SimpleXMLElement(
        '<?xml version="1.0" encoding="UTF-8"?>'
        . '<documents xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">'
        . '</documents>'
    );

    $recordTag = $root->addChild('record');

    $elementSetTag = $recordTag->addChild('elementSet');
    $elementSetTag->addAttribute('name', 'Dublin Core');

    // 2.2 retrieving data
    foreach (BisRecord::ELEMENTS as $element) {
        $records->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/' . $element);
        $els = $records->xpath('//dc:'.$element);
        foreach($els as $el) {
            $elementType = 'Bis' . ucfirst($element);
            
            $elementTagContent = new $elementType( (string) $el);
            if ($elementTagContent->getPrintMe()) {
                $elementTag = $elementSetTag->addChild('element');
                $elementTag->addAttribute('name', $elementTagContent->getName());

                // add embedded tag
                if ($elementTagContent->hasTag()) {
                    $dataTag = $elementTag->addChild('data');
                    $embeddedTag = $dataTag->addChild($elementTagContent->getTag(), $elementTagContent->getFormattedData());

                    // add embedded tag attributes
                    foreach($elementTagContent->getTagAttributes() as $name => $value) {
                        $embeddedTag->addAttribute($name, $value);
                    }
                } else {
                    $dataTag = $elementTag->addChild('data', $elementTagContent->getFormattedData());
                }
            }
        }
    }



    // 2.4 reformat to more human readable xml using DOMDocument
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

	// 2.5 print everything to new file
	$dom->loadXML($root->asXML());
    $dom->save($record->getExportFile());


