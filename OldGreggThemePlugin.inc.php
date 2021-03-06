<?php

/**
 * @file plugins/themes/oldGregg/OldGreggThemePlugin.inc.php
 *
 * Copyright (c) 2017 Vitaliy Bezsheiko, MD
 * Distributed under the GNU GPL v3.
 *
 * @class OldGreggThemePlugin
 *
 * @brief Old Gregg theme is developed on the basis of bootstrap 4; it has build-in fucntionality of JATS Parser Plugin and browse latest articles plugin
 */
import('lib.pkp.classes.plugins.GenericPlugin');
import("plugins.themes.oldGregg.jatsParser.main.Body");
import("plugins.themes.oldGregg.jatsParser.main.Back");

import('lib.pkp.classes.plugins.ThemePlugin');

class OldGreggThemePlugin extends ThemePlugin
{
	/**
	 * Initialize the theme's styles, scripts and hooks. This is only run for
	 * the currently active theme.
	 *
	 * @return null
	 */
	public function init()
	{

		$this->addStyle('bootstrap', 'bootstrap/css/bootstrap.min.css');
		$this->addStyle('header', 'css/header.css');
		$this->addStyle('footer', 'css/footer.css');
		$this->addStyle('issue', 'css/issue.css');
		$this->addStyle('site-wide', 'css/main.css');
		$this->addStyle('index', 'css/index.css');
		$this->addStyle('article', 'css/article.css');

		$this->addScript('jquery', 'jquery/jquery.min.js');
		$this->addScript('popper', 'bootstrap/js/popper.min.js');
		$this->addScript('bootstrap', 'bootstrap/js/bootstrap.min.js');
		$this->addScript('fontawesome', 'js/fontawesome-all.min.js');
		$this->addScript('article', 'js/article.js');

		$this->addStyle(
			'my-custom-font1',
			'//fonts.googleapis.com/css?family=Lora',
			array('baseUrl' => 'https://fonts.googleapis.com/css?family=Lora" rel="stylesheet'));

		$this->addStyle(
			'my-custom-font2',
			'//fonts.googleapis.com/css?family=PT+Serif',
			array('baseUrl' => ''));

		$this->addStyle(
			'my-custom-font3',
			'//fonts.googleapis.com/css?family=Arimo',
			array('baseUrl' => ''));
		$this->addStyle(
			'my-custom-font4',
			'//fonts.googleapis.com/css?family=Alegreya',
			array('baseUrl' => ''));
		$this->addStyle(
			'my-custom-font5',
			'//fonts.googleapis.com/css?family=Play',
			array('baseUrl' => ''));
		$this->addStyle(
			'my-custom-font6',
			'//fonts.googleapis.com/css?family=Source+Sans+Pro',
			array('baseUrl' => ''));
		$this->addStyle(
			'my-custom-font7',
			'//fonts.googleapis.com/css?family=Alegreya+Sans',
			array('baseUrl' => ''));

		$this->addMenuArea(array('primary', 'user'));

		HookRegistry::register('TemplateManager::display', array($this, 'jatsParser'), HOOK_SEQUENCE_NORMAL);
		HookRegistry::register('TemplateManager::display', array($this, 'browseLatest'), HOOK_SEQUENCE_CORE);
	}

	/**
	 * Get the display name of this plugin
	 * @return string
	 */
	function getDisplayName()
	{
		return __('plugins.themes.oldGregg.name');
	}

	/**
	 * Get the description of this plugin
	 * @return string
	 */
	function getDescription()
	{
		return __('plugins.themes.oldGregg.description');
	}

	/** For displaying article's JATS XML */
	public function jatsParser($hookName, $args)
	{

		// Retrieve the TemplateManager and the template filename
		$smarty = $args[0];
		$template = $args[1];

		// Don't do anything if we're not loading the right template
		if ($template != 'frontend/pages/article.tpl') {
			return;
		}

		$articleArrays = $smarty->get_template_vars('article');

		foreach ($articleArrays->getGalleys() as $galley) {
			if ($galley && in_array($galley->getFileType(), array('application/xml', 'text/xml'))) {
				$xmlGalleys[] = $galley;
			}
		}

		// Return false if no XML galleys available
		if (!$xmlGalleys) {
			return false;
		}

		$xmlGalley = null;
		foreach ($xmlGalleys as $xmlNumber => $xmlGalleyOne) {
			if ($xmlNumber > 0) {
				if ($xmlGalleyOne->getLocale() == AppLocale::getLocale()) {
					$xmlGalley = $xmlGalleyOne;
				}
			} else {
				$xmlGalley = $xmlGalleyOne;
			}
		}

		// Parsing JATS XML
		$document = new DOMDocument;
		$document->load($xmlGalley->getFile()->getFilePath());
		$xpath = new DOMXPath($document);

		$body = new Body();
		$sections = $body->bodyParsing($xpath);

		/* Assigning references */
		$back = new Back();
		$references = $back->parsingBack($xpath);

		// Assigning variables to article template
		$smarty->assign('sections', $sections);
		$smarty->assign('references', $references);
		$smarty->assign('path_template', $this->getTemplatePath());

		// retrieving embeded files
			$submissionFile = $xmlGalley->getFile();
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			import('lib.pkp.classes.submission.SubmissionFile'); // Constants
			$embeddableFiles = array_merge(
				$submissionFileDao->getLatestRevisions($submissionFile->getSubmissionId(), SUBMISSION_FILE_PROOF),
				$submissionFileDao->getLatestRevisionsByAssocId(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId(), $submissionFile->getSubmissionId(), SUBMISSION_FILE_DEPENDENT)
			);
			$referredArticle = null;
			$articleDao = DAORegistry::getDAO('ArticleDAO');
			$imageUrlArray = array();
			foreach ($embeddableFiles as $embeddableFile) {
				$params = array();
				if ($embeddableFile->getFileType()=='image/png' || $embeddableFile->getFileType()=='image/jpeg') {
					// Ensure that the $referredArticle object refers to the article we want
					if (!$referredArticle || $referredArticle->getId() != $galley->getSubmissionId()) {
						$referredArticle = $articleDao->getById($galley->getSubmissionId());
					}
					$fileUrl = Application::getRequest()->url(null, 'article', 'download', array($referredArticle->getBestArticleId(), $galley->getBestGalleyId(), $embeddableFile->getFileId()), $params);
					$imageUrlArray[$embeddableFile->getOriginalFileName()] = $fileUrl;
				}
		}
		$smarty->assign('imageUrlArray', $imageUrlArray);
	}

	/* For retrieving articles from the database */
	public function browseLatest($hookName, $args)
	{
		$smarty = $args[0];
		$template = $args[1];

		if ($template != 'frontend/pages/indexJournal.tpl') return false;

		$rangeArticles = new DBResultRange(20, 1);
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$publishedArticleObjects = $publishedArticleDao->getPublishedArticlesByJournalId($journalId = null, $rangeArticles, $reverse = true);

		$publishedArticles = array();

		while ($publishedArticle = $publishedArticleObjects->next()) {
			$publishedArticles[] = $publishedArticle;
		}
		$smarty->assign('publishedArticles', $publishedArticles);
	}
}

?>