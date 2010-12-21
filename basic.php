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
	const TOKEN_WORD = 1;
	const TOKEN_NUMBER = 2;
	const TOKEN_STRING = 3;
	const TOKEN_LABEL = 4;
	const TOKEN_NEWLINE = 5;
	const TOKEN_EQUALS = 6;
	const TOKEN_OPERATOR = 7;
	const TOKEN_LEFTOKEN_PARENTHESIES = 8;
	const TOKEN_RIGHTOKEN_PARENTHESIES = 9;
	const TOKEN_EOF = 10;
	
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
		die(var_dump($tokens));
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
			"\n" => TOKEN_NEWLINE,
			"=" => TOKEN_EQUALS,
			"+" => TOKEN_OPERATOR,
			"-" => TOKEN_OPERATOR,
			"*" => TOKEN_OPERATOR,
			"/" => TOKEN_OPERATOR,
			"<" => TOKEN_OPERATOR,
			">" => TOKEN_OPERATOR,
			"(" => TOKEN_LEFT_PARENTHESIES,
			")" => TOKEN_RIGHT_PARENTHESIES
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
					// Is our character inside the single character tokens array? If
					// so, get the token type and add a new token.
					if (isset($character_tokens[$char])) {
						$tokens[] = new Token($char, $character_tokens[$char]);
					}
					
					// Is our character a letter? If it is, we're about to start a 'word'.
					// Words can represent either a label (for gotos) or a variable
					else if (ctype_alpha($char)) {
						$token .= $char;
						$state = S_WORD;
					}
					
					// Is our character a digit? If so, we're about to start a number.
					else if (is_numeric($char)) {
						$token .= $char;
						$state = S_NUMBER;
					}
					
					// Is our character a quote? We're about to start a string
					else if ($char == '"') {
						$state = S_STRING;
					}
					
					// Is our character a single quote? Comment time
					else if ($char == "'") {
						$state = S_COMMENT;
					}
					
					break;
				
				/**
				 * The "word" state. We check the next character. If it's a letter or digit,
				 * continue the word. If it ends with a colon, it's a label, otherwise it's a word.
				 */
				case S_WORD:
					// Is our character a letter or digit? If it is, we're continuing the word
					if (ctype_alnum($char)) {
						$token .= $char;
					}
					
					// Is our character a colon? It's a label
					else if ($char == ":") {
						$tokens[] = new Token($token, TOKEN_LABEL);
						$token = "";
						$state = S_DEFAULT;
					}
					
					// Our word has ended
					else {
						// Add the token
						$tokens[] = new Token($token, TOKEN_WORD);
						
						// Reset the state
						$token = "";
						$state = S_DEFAULT;
						
						// Reprocess the current character in S_DEFAULT
						$i--;
					}
					
					break;
					
				/**
				 * The number state. If the next character is numeric, we're continuing the number.
				 * Otherwise, add the new token.
				 */
				case S_NUMBER:
					// Is it numeric?
					if (is_numeric($char)) {
						$token .= $char;
					}
					
					// We're done. Add the token
					else {
						// Add the token
						$tokens[] = new Token($token, TOKEN_NUMBER);
						
						// Reset the state
						$token = "";
						$state = S_DEFAULT;
						
						// Reprocess the current character in S_DEFAULT
						$i--;
					}
					
					break;
					
				/**
				 * The string state. Any character can be in a string except a quote, so whack it on.
				 */
				case S_STRING:
					// Is it a quote?
					if ($char == '"') {
						// Add the token
						$tokens[] = new Token($token, TOKEN_STRING);
						
						// Reset the state
						$token = "";
						$state = S_DEFAULT;
					}
					
					// Continue with our string
					else {
						$token .= $char;
					}
					
				/**
				 * The comment state. Comments are terminated by a newline, so check for that. We're just
				 * ignoring it if it's a comment, because the parser doesn't give a damn.
				 */
				case S_COMMENT:
					// Is it a newline?
					if ($char == "\n") {
						// Reset the state
						$state = S_DEFAULT;
					}
					
					break;
			}
		}
		
		return $tokens;
	}
}

/**
 * Token represents a single token in the lexer.
 * It's just a simple structure to store data.
 *
 * @package basic
 * @author Jamie Rumbelow
 **/
class Token {
	public $token;
	public $type;
	
	public function __construct($token, $type) {
		$this->token = $token;
		$this->type = $type;
	}
	
	public function __tostring() {
		return (string)$this->type . ": <" . $this->token . ">";
	}
}