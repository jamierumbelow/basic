<?php
/**
 * Basic
 *
 * A simple BASIC interpreter written in PHP
 *
 * @author Jamie Rumbelow <http://jamierumbelow.net>
 * @version 1.0.0
 * @copyright Copyright (c) 2010 Jamie Rumbelow
 * @license MIT License
 * @package basic
 **/

// We need a file argument
if (!isset($argv[1])) {
	echo "\033[0;32mUsage: php basic.php <file>\n";
	echo "\tWhere <file> is the basic file to parse\n\033[0m";
}

// Get the file
$source = file_get_contents($argv[1]);

// Create a new parser
$basic = new Basic();
$basic->interpret($source);

/**
 * The main Basic class
 *
 * @package basic
 * @author Jamie Rumbelow
 **/
class Basic {
	
	/**
	 * A big long list of constants for tokens. Each token represents
	 * something in the class
	 */
	const T_WORD = 1;
	const T_NUMBER = 2;
	const T_STRING = 3;
	const T_LABEL = 4;
	const T_NEWLINE = 5;
	const T_EQUALS = 6;
	const T_OPERATOR = 7;
	const T_LEFT_PARENTHESIES = 8;
	const T_RIGHT_PARENTHESIES = 9;
	const T_EOF = 10;
	
	/**
	 * These constants represent the tokeniser's current state; the
	 * tokeniser is built as a state machine. To clarify, if we are
	 * tokenising and we come across a string, we need to remember
	 * that we're inside a string. We then need to break out of it when
	 * the string is terminated.
	 */
	const S_DEFAULT = 1;
	const S_WORD = 2;
	const S_NUMBER = 3;
	const S_STRING = 4;
	const S_COMMENT = 5;
	
	/**
	 * This function runs the BASIC source through the interpretation
	 * pipeline; tokenising, parsing and executing the code.
	 *
	 * @param string $source The BASIC source code
	 * @return void
	 * @author Jamie Rumbelow
	 */
	public function interpret($source) {
		// Tokenise
		$tokens = $this->tokenise($source);
		
		// Parse
		$parser = new Parser($tokens);
		$statements = $parser->parse();
		
		// Loop through the statements and execute them
		foreach ($statements as $statement) {
			$statement->execute();
		}
	}
	
	/**
	 * This function tokenises the source code. Tokenising, or lexing, involves
	 * looking through the source code and replacing the syntax with tokens that the
	 * parser can read quickly. Each token represents something meaningful to
	 * the program, like a variable, operator or string.
	 *
	 * @param string $source The source code
	 * @return array $tokens The array of tokens
	 * @author Jamie Rumbelow
	 **/
	public function tokenise($source) {
		// Our final array of tokens
		$tokens = array();
		
		// The current state of our tokeniser
		$state = S_DEFAULT;
		$token = "";
		
		// Keep a one-to-one mapping of all the single-character tokens here
		// in an array that we can pull out later.
		$character_tokens = array(
			"\n" => T_NEWLINE,
			"=" => T_EQUALS,
			"+" => T_OPERATOR,
			"-" => T_OPERATOR,
			"*" => T_OPERATOR,
			"/" => T_OPERATOR,
			"<" => T_OPERATOR,
			">" => T_OPERATOR,
			"(" => T_LEFT_PARENTHESIES,
			")" => T_RIGHT_PARENTHESIES
		);
		
		// Scan through each character of the source code at
		// a time and build up a tokenised representation of the source
		for ($i = 0; $i < strlen($source); $i++) {
			// Get the current character
			$char = $source{$i};
			
			// Switch the state
			switch ($state) {
				
				/**
				 * The "default" state: routine code parsing. We can use this opportunity
				 * to check for single-char tokens, as well as change state if we need to.
				 */
				case S_DEFAULT:
					// Is our token inside the single character tokens array? If
					// so, get the token type and add a new token.
					if (isset($character_tokens[$char])) {
						$tokens[] = new Token($char, $character_tokens[$char]);
					}
					
					// Is our token a letter? If it is, we're about to start a 'word'.
					// Words can represent either a label (for gotos) or a variable
					else if (ctype_alpha($char)) {
						$token .= $char;
						$state = S_WORD;
					}
					
					// Is our token a digit? If so, we're about to start a number.
					else if (is_numeric($char)) {
						$token .= $char;
						$state = S_NUMBER;
					}
					
					// Is our token a quote? We're about to start a string
					else if ($char == '"') {
						$state = S_STRING;
					}
					
					// Is our token a single quote? Comment time
					else if ($char == "'") {
						$state = S_COMMENT;
					}
					
					break;
				
				default:
					# code...
					break;
			}
		}
		
		return $tokens;
	}
}