<?php

/**
 * @package  OneCRMPortalRenderer
 */

namespace OneCRM\Portal\Renderer;

class Pagination {

	// should be odd
	const MAX_LINKS = 5;

	var $curPage;
	var $perPage;
	var $total;
	var $template;


	public function __construct($curPage, $perPage, $totalRecords, $template) {
		$this->curPage = (int)$curPage;
		$this->perPage = (int)$perPage;
		$this->totalRecords = (int)$totalRecords;
		$this->template = $template;
	}

	public function render() {
		if ($this->totalRecords < $this->perPage) return '';

		$totalPages = (int)(($this->totalRecords + $this->perPage - 1) / $this->perPage);

		$displayFirst = $this->curPage > (int)(self::MAX_LINKS / 2) + 1;
		$displayFirstSep = $this->curPage > (int)(self::MAX_LINKS / 2) + 2;

		$firstRecord = ($this->curPage - 1) * $this->perPage + 1;
		$lastRecord = $firstRecord + $this->perPage - 1;
		if ($lastRecord > $this->totalRecords) {
			$lastRecord = $this->totalRecords;
		}

		$start = $this->curPage - (int)(self::MAX_LINKS / 2);
		while ($start + (int)(self::MAX_LINKS) > $totalPages + 1)
			$start--;
		if ($start < 1) {
			$start = 1;
		}
		$links = [
			sprintf(
				__(
					'Displaying %1$s-%2$s of %3$s'
					/*
						translators: 
							%1$s is the first record displayed in pages range; 
							%2$s is the last record displayed in pages range; 
							%3$s is the total number of records
					* */
					, ONECRM_P_TEXTDOMAIN),
				$firstRecord, $lastRecord, $this->totalRecords
			),
		];
		if ($displayFirst) {
			$links[] = $this->renderPageLink(1);
		}
		if ($displayFirstSep) {
			$links[] = '<span class="onecrm-p-page-number-separator"></span>';
		}
		for ($page = $start; $page < $start + self::MAX_LINKS && $page <= $totalPages; $page ++) {
			$links[] = $this->renderPageLink($page);
		}
		if ($page < $totalPages) {
			$links[] = '<span class="onecrm-p-page-number-separator"></span>';
		}
		if ($page < $totalPages+1) {
			$links[] = $this->renderPageLink($totalPages);
		}
		return '<div class="onecrm-p-pagination">'
			. join(' ', $links)
			. '</div>'
			;
	}

	protected function renderPageLink($pageNumber) {
		$cls = $pageNumber == $this->curPage ? 'current' : '';
		return '<a class="onecrm-p-page-number ' . $cls . '" href="#" onclick="return OneCRM.Portal.App.Pagination.load(\''. sprintf($this->template, $pageNumber) .'\');">' . $pageNumber . '</a>';
	}

}

