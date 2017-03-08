<?php
    class BisRow {
        const HEADERS = [
            "creator" => "CREATOR",
            "title" => "TITLE",
            "title2" => "TITLE",
            "date" => "DATE",
            "source" => "SOURCE",
            "source2" => "SOURCE",
            "contributor" => "CONTRIBUTOR",
            "publisher" => "PUBLISHER",
            "format" => "FORMAT",
            "language" => "LANGUAGE",
            "format2" => "FORMAT",
            "description" => "DESCRIPTION",
            "description2" => "DESCRIPTION",
            "description3" => "DESCRIPTION",
            "description4" => "DESCRIPTION",
        ];

        const DELIMITER = "\t";

        const ORIGINAL_COLUMNS= [
            "creator",
            "title",
            "title2",
            "date",
            "source",
            "source2",
            "skip",
            "format",
            "language",
            "format2",
            "description",
            "description2",
            "description3",
            "description4",
        ];

        protected $columnCount = [
            "creator" => 1,
            "title" => 1,
            "title2" => 1,
            "date" => 1,
            "source" => 1,
            "source2" => 1,
            "skip" => 4,
            "format" => 1,
            "language" => 1,
            "format2" => 1,
            "description" => 1,
            "description2" => 1,
            "description3" => 1,
            "description4" => 1,
        ];

        protected $originalFile = "original.csv";
        protected $exportFile = "export_file.csv";

        function __construct($originalFile=null, $exportFile=null) {
            if ($originalFile) {
                $this->originalFile = $originalFile;
            }

            if ($exportFile) {
                $this->exportFile = $exportFile;
            }

            $this->countColumns();
        }

        public function getOriginalFile() {
            return $this->originalFile;
        }

        public function getExportFile() {
            return $this->exportFile;
        }

        public function getColumnCount() {
            return $this->columnCount;
        }

        protected function getNbOfColumns($value, $delimiter="\\;\\") {
            return (substr_count($value, $delimiter) + 1);
        }

        protected function countColumns() {
            $columnCount = $this->columnCount;

            if (($handle = fopen($this->originalFile, "rb")) !== false) {

                // skip first line (csv header)
                fgetcsv($handle);

                while (($line = fgets($handle)) !== false) {
                    // process the line read
                    $line = substr(trim($line), 1, -1);
                    $values = explode("\\,\\", $line);
                    #echo "nb of values: ", count($values), "\n";
                    
                    foreach (self::ORIGINAL_COLUMNS as $column) {
                        // #todo: remove condition if too slow
                        if ($column != "skip" && $column != "source") {
                            $columnCount[$column] =
                                max($columnCount[$column],
                                    $this->getNbOfColumns(
                                        $values[array_search(
                                            $column, self::ORIGINAL_COLUMNS)])
                                    );
                        }
                    }
                }
                fclose($handle);

                $this->columnCount = $columnCount;
            } else {
                // error opening the file
            }
        }

        public function printHeaders() {
            $columnCount = $this->getColumnCount();

            $exportFile = $this->getExportFile();
            $headers = [];
    
            foreach ($columnCount as $column => $count) {
                // special case: skip -> contributor and publisher
                if ($column == "skip") {
                    for ($i=1; $i<= $count; $i++) {
                        $headers[] = BisRow::HEADERS['contributor'];
                    }
                    for ($i=1; $i<= $count; $i++) {
                        $headers[] = BisRow::HEADERS['publisher'];
                    }
                } else {
                    for ($i=1; $i<= $count; $i++) {
                        $headers[] = BisRow::HEADERS[$column];
                    }
                }
            }

            $handle = fopen($exportFile, "wb");
            fwrite($handle, implode(BisRow::DELIMITER, $headers)."\n");
            fclose($handle);
        }

        public function printData() {
            $originalFile = fopen($this->originalFile, "rb");
            // skip first line (csv header)
            fgetcsv($originalFile);

            $exportFile = fopen($this->exportFile, "ab");

            while (($line = fgets($originalFile)) !== false) {
                $data = [];
                $line = iconv("WINDOWS-1252", "UTF-8", $line);
                // process the line read
                $line = substr(trim($line), 1, -1);
                $values = explode("\\,\\", $line);

                for ($i=0, $nbOfValues=count($values); $i < $nbOfValues; $i++) {
                    $column = BisRow::ORIGINAL_COLUMNS[$i];
                    $elementType = "Bis".ucfirst($column);
                    $element = new $elementType(
                        $values[$i], 
                        $this->columnCount[$column]
                    );
                    $data = array_merge($data, $element->getFormattedData());
                }
                fwrite($exportFile, implode(self::DELIMITER, $data)."\n");
            }
            fclose($originalFile);
            fclose($exportFile);
        }
    }

    abstract class BisElement {
        const DELIMITER = "\\;\\";
        protected $pattern = "/(.*)/";  # regex to look for values
        protected $rawData;     # string which may contain multiple values
        protected $columnCount; # nb of values needed
        protected $data = [];   # array of values
        protected $nbOfValues;  # nb of values that were separated by delimiter
        protected $formattedData = [];  # processed values

        function __construct($rawData, $columnCount) {
            $this->rawData = $rawData;
            $this->columnCount = $columnCount;
            $this->splitData();
            $this->processData();
        }

        protected function splitData() {
            $data = trim($this->rawData);
            $this->data = explode(self::DELIMITER, $data);
            $this->nbOfValues = min($this->columnCount, count($this->data));
        }

        // for each value in data: make calls to format the data
        protected function processData() {
            for ($i=0; $i < $this->nbOfValues; $i++) {
                $matches = [];
                if (preg_match($this->pattern, $this->data[$i], $matches)) {
                    array_shift($matches);
                    $formattedData = $this->formatData($matches);
                    $this->formattedData[] = $formattedData;
                } else {
                    // log current line in separate file
                }
            }

            while (count($this->formattedData) < $this->columnCount) {
                $this->formattedData[] = '';
            }
        }

        // format a single value
        protected function formatData($matches) {
            return implode(" ", $matches);
        }

        public function getFormattedData() {
            return $this->formattedData;
        }
    }

    class BisCreator extends BisElement {
        #protected $pattern = "/(?:[0-9]{8}.)?\s*([^,]+,)\s*([\S]*)\s*(\([^\)]*\))?/";
        protected $flags = [];  # to keep track of what we've found

        protected function processData() {
            for ($i=0; $i < $this->nbOfValues; $i++) {
                $formattedData = $this->formatData($this->data[$i]);
                $this->formattedData[] = $formattedData;
            }

            // make sure we have the right number of values
            while (count($this->formattedData) < $this->columnCount) {
                $this->formattedData[] = '';
            }
        }

        protected function formatData($data) {
            $formattedElement = "";

            // 0. everytime we remove something, we trim the new string
            // 1. if field starts with 8 digits + 1 digit/1 letter + space: remove them
            $toRemove = "/(?:[0-9]{8}.\s+)/";
            // 2.1 if date, save then remove everything that is after it
            $regexDate = "/(\()?(\d{2}.{2}-\d{2}.{2})(\)?)/";
            // 2.2 if !date, remove everything after second space

            // 1.
            $data = preg_replace($toRemove, "", $data);

            // 2.
            $count = 0;
            $matches = [];
            $date = '';
            
            if (preg_match($regexDate, $data, $matches, PREG_OFFSET_CAPTURE)) {
                $this->flags['date'] = 1;
                $date = $matches[2][0];
                $datePosition = $matches[0][1];
                $data = trim(substr($data, 0, $datePosition));
            } else {
                $this->flags['date'] = 0;
                // remove everything after 2nd space after comma
                $removeFrom = '/,\s*\S+(\s)/';
                if (preg_match($removeFrom, $data, $matches, PREG_OFFSET_CAPTURE)) {
                    $removePos = $matches[0][1];
                    $data = trim(substr($data, 0, $removePos));
                }
            }

            // construct element with all the parts found
            $formattedElement = $data;
            if ($this->flags['date']) {
                $formattedElement .= ' (' . $date . ')';
            }

            return $formattedElement;
        }
    }

    class BisTitle extends BisElement {
    }
    
    class BisTitle2 extends BisElement {

        protected function formatData($matches)
        {
            return "Autre titre : ".implode($matches);
        }
    }

    class BisDate extends BisElement {
        protected $pattern = "/(?:.{9}(\d{4}))(\d{4})?/";

        protected function formatData($matches) {
            return implode("-", $matches);
        }
    }

    class BisSource extends BisElement {
        protected $pattern = "/(^.{9}$)/";

        protected function formatData($matches) {
            $format = '<a title="Lien vers la notice SUDOC" href=" http://www.sudoc.fr/%s" target="_blank">Description bibliographique (SUDOC)</a>';
            return sprintf($format, implode($matches));
        }
    }

    class BisSource2 extends BisElement {

        protected function formatData($matches) {
            $format = "Bibliothèque interuniversitaire de la Sorbonne, cote : %s";
            return sprintf($format, $matches[0]);
        }
    }

    class BisSkip extends BisElement {
        protected $flags = [];  # to keep track of what we've found

        # too difficult for one single regex so multiple passes with simpler regex cf formatData()
        #$pattern = "/(?:\d{8}[[:alnum:]]\s?)([^(]*?(\(.{4}-.{4}\)?)?(?=\s{2,}))\s{2,}(\(?.*?(?=\s{2,}))\s{2,}([^.]*)/";

        
        protected function processData() {
            $publisherData = [];
            $nbOfValues = min($this->columnCount * 2, count($this->data));

            for ($i=0; $i < $nbOfValues; $i++) {

                $formattedData = $this->formatData($this->data[$i]);
                if (strpos($formattedData, "Imprimeur")) {
                    $publisherData[] = $formattedData;
                } else {
                    $this->formattedData[] = $formattedData;
                }
            }

            // make sure we have the right number of values
            while (count($this->formattedData) < $this->columnCount) {
                $this->formattedData[] = '';
            }
            $this->formattedData = array_slice($this->formattedData, 0, $this->columnCount);

            while (count($publisherData) < $this->columnCount) {
                $publisherData[] = '';
            }

            $publisherData = array_slice($publisherData, 0, $this->columnCount);
            $this->formattedData = array_merge($this->formattedData, $publisherData);
        }

        protected function formatData($data) {
            $formattedElement = "";

            // 0. everytime we remove something, we trim the new string
            // 1. if field starts with 8 digits + 1 digit/1 letter: remove them
            $toRemove = "/^\d{8}[[:alnum:]]/i";
            // 2. if imprimeur, libraire or a combination of them, flag them then remove them
            $imprimeur = "/(\(?imprimeur(?!-)\)?)/i";
            $imprimeurLibraire = "/(\(?imprimeur-libraire\)?)/i";
            // 3. if proprietaire precedent: flag it then remove it
            $previousOwner = "/(\(?Propriétaire précédent\)?)/i";
            // 4. if date, save then remove
            #$date = "/(\(?\d{2}.{2}-\d{2}.{2}\)?)/";
            $date = "/(\()?(\d{2}.{2}-\d{2}.{2})(\)?)/";
            // 5. explode by '  ': index 0 = title; index 1 = city

            // 1.
            $data = preg_replace($toRemove, "", $data);

            // 2.
            $count = 0;
            $data = preg_replace($imprimeur, "", $data, 1, $count);
            $this->flags['imprimeur'] = $count ? 1 : 0;
            $count = 0;
            $data = preg_replace($imprimeurLibraire, "", $data, 1, $count);
            $this->flags['imprimeurLibraire'] = $count ? 1: 0;

            // 3.
            $count = 0;
            $data = preg_replace($previousOwner, "", $data, 1, $count);
            $this->flags['previousOwner'] = $count ? 1: 0;

            // 4.
            $count = 0;
            $matches = [];
            
            if (preg_match($date, $data, $matches)) {
                $this->flags['date'] = $matches[2];
                $data = preg_replace($date, "", $data);
            } else {
                $this->flags['date'] = 0;
            }

            // 5.
            $remainingTerms = explode('  ', $data);
            $this->flags['title'] = $remainingTerms[0] ?: 0;
            $this->flags['city'] = $remainingTerms[1] ?: 0;
            
            // if $remainingTerms[2] log error? #todo

            // construct element with all the parts found
            $formattedElement = trim($this->flags['title']) ?: '';

            if ($date = $this->flags['date']) {
                $formattedElement .= ' (' . $date . ')';
            }

            if ($city = $this->flags['city']) {
                if ($city[0] == '(') {
                    $formattedElement .= ' ' . $city . '. ';
                } else {
                    $formattedElement .= '. ' . $city . '  ';
                }
            } else {
                $formattedElement .= '. ';
            }

            $formattedElement .= $this->flags['previousOwner'] ? 'Propriétaire précédent': '';

            $formattedElement .= $this->flags['imprimeur'] ? 'Imprimeur': '';

            if ($this->flags['imprimeur'] && $this->flags['imprimeurLibraire']) {
                $formattedElement .= ' / ';
            } 
            $formattedElement .= $this->flags['imprimeurLibraire'] ? 'Imprimeur-libraire' : '';

            return $formattedElement;
        }
    }

    // column 'Collation'
    class BisFormat extends BisElement {
    }

    // column 'Langue'
    class BisLanguage extends BisElement {
    }

    // column 317
    class BisFormat2 extends BisElement {
    }

    // column 423
    class BisDescription extends BisElement {
        protected $pattern = "/(.+)/";

        protected function formatData($matches)
        {
            return "Est publié avec : ".implode($matches);
        }
    }

    // column 463
    class BisDescription2 extends BisElement {
        protected $pattern = "/(.+)/";

        protected function formatData($matches)
        {
            return "Comprend : ".implode($matches);
        }
    }

    // column 464
    class BisDescription3 extends BisElement {
        protected $pattern = "/(.+)/";

        protected function formatData($matches)
        {
            return "Contient : ".implode($matches);
        }
    }

    // column 461
    class BisDescription4 extends BisElement {
        protected $pattern = "/(.+)/";

        protected function formatData($matches)
        {
            return "Dans : ".implode($matches);
        }
    }




    // main program
    $originalFile = isset($argv[1]) ? $argv[1] : "";
    $exportFile = isset($argv[2]) ? $argv[2] : "";

    // 1. scan csv file to get the number of columns for each value
    $bisRow = new BisRow($originalFile, $exportFile);

    // 2. create tsv header
    $bisRow->printHeaders();

    // 3. populate tsv file
    $bisRow->printData();






    
