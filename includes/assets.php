<?php

/**
 * Represents a single JS or CSS file
 *
 */
class Asset {
	protected $filepath;
	protected $extension;


	function __construct($filepath) {
		$this->filepath = $filepath;
		$path_parts = pathinfo($this->filepath);
		$this->extension = strtolower($path_parts['extension']);
	}


	function exists() {
		return file_exists($this->filepath);
	}


	function getExtension() {
		return $this->extension;
	}
}


/**
 *  Represents a JS or CSS file that will
 *  be minified and/or combined
 *
 */
class ChildAsset extends Asset {
	protected $parent;
	protected $minify;


	function __construct($filepath, $minify) {
		parent::__construct($filepath);
		$this->minify = $minify;
	}


	function setParent ($parent_filepath) {
		$this->parent = $parent_filepath;
	}
}


/**
 *  Represents the result of combining one
 *  or more JS or CSS files (aka child assets)
 *
 */
class ParentAsset extends Asset {
	protected $children = array(); // contains ChildAsset objects
	protected $children_str = '';  // space-delimited list of child files

	function addChild($filepath, $minify=true) {
		$this->children_str .= $filepath . ' ';

		$child = new ChildAsset($filepath, $minify);
		$child->setParent($this->filepath);

		$this->children[] = $child;
	}


	// Return an array or string of child assets
	function getChildren($return_str=false) {
		return ($return_str ? $this->children_str : $this->children);
	}
}
