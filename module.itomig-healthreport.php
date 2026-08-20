<?php
//
// iTop module definition file
//

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'itomig-healthreport/26.3.0',
	array(
		// Identification
		//
		'label' => 'Healthcheck - Report-Auswertung (ITOMIG GmbH)',
		'category' => 'business',

		// Setup
		//
		'dependencies' => array(
			// Definiert Profil "Portal user" (id=2), auf das user_rights unten
			// zugreift. Modul ist in Standard-iTop-Installationen "mandatory".
			'itop-profiles-itil/3.2.0',
		),
		'mandatory' => false,
		'visible' => true,

		// Components
		//
		'datamodel' => array(
			'vendor/autoload.php',
			'model.itomig-healthreport.php',
			'src/Controller/HealthReportController.php',
			// Registriert die Portal-Route der Upload-Brick; wirkt nur,
			// wenn itop-portal-base installiert ist (siehe Datei selbst).
			'portal-bootstrap.php',
		),
		'webservice' => array(

		),
		'data.struct' => array(
			// add your 'structure' definition XML files here,
		),
		'data.sample' => array(
			// add your sample data XML files here,
		),

		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any

		// Default settings
		//
		'settings' => array(
			// Module specific settings go here, if any
		),
	)
);

?>
