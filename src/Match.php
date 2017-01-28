<?php

namespace alejoluc\Via;

class Match {

	private $matchFound;
	private $destination;
	private $filters;
	private $filterErrors;

    /** @var Request  */
    private $request;

	public function __construct() {
		$this->matchFound  = false;
		$this->destination = null;
        $this->request     = null;

		$this->filters  	 = [];
		$this->filterErrors  = [];
	}

	public function setDestination($destination) {
		$this->destination = $destination;
	}

	public function getDestination() {
		if (!$this->isMatch()) {
			throw new ViaException('Can\'t retrieve result for inexistent match');
		}
		if (!$this->filtersPass()) {
			throw new ViaException('Can\'t access routing result when a filter fails');
		}
		return $this->destination;
	}

	public function setRequest(Request $request) {
	    $this->request = $request;
    }

    public function getRequest() {
        return $this->request;
    }

	public function setMatchFound($matchFound) {
		$this->matchFound = $matchFound;
	}

	public function isMatch() {
		return $this->matchFound === true;
	}

	public function getFilters() {
		return $this->filters;
	}

	public function setFilters($filters) {
		$this->filters = $filters;
	}

	public function filtersPass() {
		return count($this->getFilterErrors()) === 0;
	}

	public function getFilterErrors() {
		return $this->filterErrors;
	}

	public function addFilterError($error) {
		$this->filterErrors[] = $error;
	}

}