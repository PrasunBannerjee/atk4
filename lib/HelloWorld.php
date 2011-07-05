<?php
/***********************************************************
   ..

   Reference:
     http://atk4.com/doc/ref

 **ATK4*****************************************************
   This file is part of Agile Toolkit 4 
    http://agiletoolkit.org
  
   (c) 2008-2011 Agile Technologies Ireland Limited
   Distributed under Affero General Public License v3
   
   If you are using this file in YOUR web software, you
   must make your make source code for YOUR web software
   public.

   See LICENSE.txt for more information

   You can obtain non-public copy of Agile Toolkit 4 at
    http://agiletoolkit.org/commercial

 *****************************************************ATK4**/
class HelloWorld extends AbstractView {
	private $message;
	function init(){
		$this->message = 'Hello world';
	}
	function setMessage($msg){
		$this->message=$msg;
	}
	function render(){
		$this->output('<p>'.$this->message.'</p>');
	}
}
