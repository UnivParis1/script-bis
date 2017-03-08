# script pour convertir un xml provenant de Calames en xml que l'on peut importer dans Omeka

## Prérequis
- avoir php installé sur son poste.
- avoir le droit d'exécuter des fichiers. 

## utilisation
Ouvrir un émulateur de terminal (ex: Terminal sur mac, cmd sur Windows, XTerm sur distributions Linux...) et taper:
    ``php script_dc_xml.php <original_file_path> <export_file_name>``

Les paramètres sont optionnels:
- la valeur par défaut du fichier d'entrée (``original_file_path``) est ``bis_xml_input.xml``
- la valeur par défaut du fichier de sortie (``export_file_name``) est ``bis_xml_output.xml``

## modification

### règles de formatage des données
La fonction ``formatData()`` des classes qui héritent de la classe ``BisElement`` permet de traiter les données que l'on va retourner.

### ajout d'éléments
Trois étapes sont nécessaires:
- ajout de ``element_name`` dans la constante de classe ``BisRecord::ELEMENTS``. ``element name`` correspond au nom du tag dans le fichier d'entrée. Implémenter la fonction ``getName()`` du nouvel élément pour modifier le nom du tag dans le fichier de sortie.
- ajout de la classe, avec pour nom ``Bis`` + ``element_name`` 
- implémentation de la fonction ``format()`` de la classe nouvellement créée
