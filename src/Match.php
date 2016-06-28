<?php

namespace Via;

class Match {

	private $matchFound;
	private $result;
	private $parameters;
	private $filters;
	private $filterError;

	public function __construct() {
		$this->matchFound = false;
		$this->result  	  = null;
		$this->parameters = [];

		$this->filters  	 = [];
		$this->filterError   = null;
	}

	public function setResult($result) {
		$this->result = $result;
	}

	public function getResult() {
		if (!$this->isMatch()) {
			throw new ViaException('Can\'t retrieve result for inexistent match');
		}
		if (!$this->filtersPass()) {
			throw new ViaException('Can\'t access routing result when a filter fails');
		}
		return $this->result;
	}

	public function setParameters($parameters) {
		$this->parameters = $parameters;
	}

	public function getParameters() {
		return $this->parameters;
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
		return $this->getFilterError() === null;
	}

	public function getFilterError() {
		return $this->filterError;
	}

	public function setFilterError($errorResult) {
		$this->filterError = $errorResult;
	}

}