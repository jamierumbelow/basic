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
	const TOKEN_EQUALS = 5;
	const TOKEN_OPERATOR = 6;
	const TOKEN_LEFT_PARENTHESIES = 7;
	const TOKEN_RIGHT_PARENTHESIES = 8;
	const TOKEN_EOF = 9;
	
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
	 * The Basic class acts as the lexer and intepreter, so we want to keep
	 * track of a few bits during interpretation, including variables
	 */
	static public $variables = array();
	static public $statements = array();
	static public $labels = array();
	static public $current_statement = 0;
	
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
		$parser->parse();
		
		// Loop through the statements and execute them
		while (self::$current_statement < count(self::$statements)) {
			self::$current_statement++;
			self::$statements[self::$current_statement]->execute();
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
					if (ctype_alnum($char) || $char == '_') {
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

/**
 * The parser takes in an array of tokens and generates
 * something called an AST (Abstract Syntax Tree). This is a
 * data structure that contains all the statements and expressions
 * inside the code.
 *
 * One of the reasons we tokenise the code first is that we can keep
 * multiple levels in the AST, whereas the tokeniser is stuck at one level.
 *
 * @package basic
 * @author Jamie Rumbelow
 **/
class Parser {
	public $tokens = array();
	public $position = 0;
	public $line = 1;
	
	public function __construct($tokens) {
		$this->tokens = $tokens;
	}
	
	/**
	 * The top level parsing function. This function loops through the
	 * tokens and routes over to other methods that handle the language.
	 *
	 * @return array
	 * @author Jamie Rumbelow
	 */
	public function parse() {
		// Keep track of statements and labels
		$statements = array();
		$labels = array();
		
		// Infinite loop; we'll use $this->position to keep
		// track of when we're done
		while (TRUE) {
			// Is this a label?
			if ($this->match(TOKEN_LABEL)) {
				// Record this label, linking it to the current index of the 
				// statements. This is so we can route the program flow later
				$labels[$this->previous()->token] = (count($statements) > 0) ? count($statements) - 1 : 0;
			}
			
			// Is it an assignment?
			else if ($this->match(TOKEN_WORD, TOKEN_EQUALS)) {
				// Create a new assignment statement with the current token text (the variable's name), and
				// parse the expression
				$this->position++;
				$statements[] = new AssignmentStatement($this->previous(1)->token, $this->expression());
			}
			
			// Is it a print statement?
			else if ($this->current()->token == "print") {
				// Parse the expression and create new print statement
				$this->position++;
				$statements[] = new PrintStatement($this->expression());
			}
			
			// Is it an input statement?
			else if ($this->current()->token == "input") {
				// Get the next token (variable name) and create new input statement
				// We're using next_token() to ensure that the next token is indeed a TOKEN_WORD.
				$statements[] = new InputStatement($this->next_token(TOKEN_WORD)->token);
				$this->position++;
				$this->position++;
			}
			
			// Is it a goto statement?
			else if ($this->current()->token == "goto") {
				// Similar to above, get the next token (label to go to) and create new goto statement
				$statements[] = new GotoStatement($this->next_token(TOKEN_WORD)->token);
				$this->position++;
				$this->position++;
			}
			
			// Is it an if statement?
			else if ($this->current()->token == "if") {
				// This is where it gets slightly more complex. We first want to parse an expression,
				// which is the condition.
				$this->position++;
				$condition = $this->expression();
				
				// Then we want the label to go to
				$label = $this->next_token(TOKEN_WORD)->token;
				$this->position++;
				$this->position++;
				
				// Create the new statement
				$statements[] = new IfThenStatement($condition, $label);
			}
			
			// Is it an exit statement?
			else if ($this->current()->token == "exit") {
				// Create new print statement
				$this->position++;
				$statements[] = new ExitStatement();
			}
			
			// We're not sure what token this is, it's probably the end of the file. So, bye!
			else {
				break;
			}
		}
		
		// Store the statements and labels in the intepreter
		Basic::$statements = $statements;
		Basic::$labels = $labels;
	}
	
	/**
	 * Get the current token
	 *
	 * @return Token
	 * @author Jamie Rumbelow
	 **/
	public function current() {
		return $this->tokens[$this->position];
	}
	
	/**
	 * Get the next token, optionally offset
	 *
	 * @return Token
	 * @author Jamie Rumbelow
	 **/
	public function next($offset = 0) {
		return $this->tokens[$this->position + 1 + $offset];
	}
	
	/**
	 * Get the previous token, optionally offset
	 *
	 * @return Token
	 * @author Jamie Rumbelow
	 **/
	public function previous($offset = 0) {
		return $this->tokens[$this->position - 1 - $offset];
	}
	
	/**
	 * Get the next token, ensuring it is a specific type
	 *
	 * @return Token
	 * @author Jamie Rumbelow
	 **/
	public function next_token($type) {
		$token = $this->tokens[$this->position + 1];
		
		// Check the token and type match
		if ($token->type == $type) {
			return $token;
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Get the next token, ensuring it is has a particular word as it's text
	 *
	 * @return Token
	 * @author Jamie Rumbelow
	 **/
	public function next_token_word($word) {
		$token = $this->tokens[$this->position + 1];
		
		// Check the token and type match
		if ($token->token == $word) {
			return $token;
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Match the current token with $token_one, and the next
	 * token with $token_two, if we pass it. Then move to the next token.
	 *
	 * If one token is passed, will return TRUE or FALSE if the current token matches.
	 * If two are passed, BOTH are required to match
	 *
	 * @param string $token_one The first token
	 * @param string | boolean $token_two The second token
	 * @return boolean
	 * @author Jamie Rumbelow
	 */
	public function match($token_one, $token_two = FALSE) {
		if (!$token_two) {
			// Compare and return
			if ($this->current()->type == $token_one) {
				// Increment the position
				$this->position++;
				
				return TRUE;
			} else {
				return FALSE;
			}
		}
		
		// We have two tokens
		else {
			// Check the first compares with the current
			if ($this->current()->type == $token_one) {
				// Check the second compares
				if ($this->next()->type == $token_two) {
					// Increment the position
					$this->position++;
					
					// And success
					return TRUE;
				} else {
					return FALSE;
				}
			} else {
				return FALSE;
			}
		}
	}
	
	/**
	 * Parse an expression. We siphon this off to operator(),
	 * as we start at the bottom of the precedence stack and rise up
	 * and binary operators (+, -, et cetera) are the lowest.
	 *
	 * @author Jamie Rumbelow
	 **/
	public function expression() {
		return $this->operator();
	}
	
	/**
	 * Parses a series of binary operator expressions into a single
	 * expression. We do this by building the expression bit by bit.
	 *
	 * @author Jamie Rumbelow
	 */
	public function operator() {
		// Look up what's to the left
		$expression = $this->atomic();
		
		// As long as we have operators, keep building operator expressions
		while ($this->match(TOKEN_OPERATOR) || $this->match(TOKEN_EQUALS)) {
			// Get the operator
			$operator = $this->previous()->token;
			
			// Look to the right, another atomic
			$right = $this->atomic();
			
			// Set the expression
			$expression = new OperatorExpression($expression, $operator, $right);
		}
		
		// Return the final expression
		return $expression;
	}
	
	/**
	 * Look for an atomic expression, which is a single literal
	 * value such as a string or or a number. It's also possible we've
	 * got another expression wrapped in parenthesis.
	 *
	 * @author Jamie Rumbelow
	 **/
	public function atomic() {
		// Is it a word? Words reference variables
		if ($this->match(TOKEN_WORD)) {
			return new VariableExpression($this->previous()->token);
		}
		
		// A number? Parse it as a float
		else if ($this->match(TOKEN_NUMBER)) {
			return new NumberExpression(floatval($this->previous()->token));
		}
		
		// A string?
		else if ($this->match(TOKEN_STRING)) {
			return new StringExpression($this->previous()->token);
		}
		
		// Left parenthesis, a new expression
		else if ($this->match(TOKEN_LEFT_PARENTHESIES)) {
			// Parse the expression and find the closing parenthesis
			$expression = $this->expression();
			$this->token_type(TOKEN_RIGHT_PARENTHESIES);
			
			// Return the expression
			return $expression;
		}
		
		// Give up & throw an error
		throw new BasicParserException("Couldn't parse expression");
	}
}

/**
 * The base Statement interface. Statements do stuff when executed
 */
interface Statement {
	public function execute();
}

/**
 * The base Expression interface. Expressions return values when evaluated
 **/
interface Expression {
	public function evaluate();
}

/**
 * A "print" statement evaluates an expression, converts the result to a
 * string, and displays it to the user.
 */
class PrintStatement implements Statement {
	public function __construct($expression) {
		$this->expression = $expression;
	}
	
	public function execute() {
		print $this->expression->evaluate() . "\n";
	}
}

/**
 * A "input" statement gets a line of input from the user and assigns it
 * to a variable.
 */
class InputStatement implements Statement {
	public function __construct($variable) {
		$this->variable = $variable;
	}
	
	public function execute() {
		Basic::$variables[$this->variable] = trim(fgets(fopen("php://stdin","r")));
	}
}

/**
 * An assignment statement assigns a variable with a value
 */
class AssignmentStatement implements Statement {
	public function __construct($variable, $value) {
		$this->variable = $variable;
		$this->value = $value;
	}
	
	public function execute() {
		Basic::$variables[$this->variable] = $this->value->evaluate();
	}
}

/**
 * A goto statement moves the program execution flow to a labelled point.
 */
class GotoStatement implements Statement {
	public function __construct($label) {
		$this->label = $label;
	}
	
	public function execute() {
		if (isset(Basic::$labels[$this->label])) {
			Basic::$current_statement = (int)Basic::$labels[$this->label];
		}
	}
}

/**
 * An if-then statement jumps to
 */
class IfThenStatement implements Statement {
	public function __construct($expression, $label) {
		$this->expression = $expression;
		$this->label = $label;
	}
	
	public function execute() {
		if ($this->expression->evaluate()) {
			$goto = new GotoStatement($this->label);
			$goto->execute();
		}
	}
}

/**
 * A simple statement to exit program flow
 */
class ExitStatement implements Statement {
	public function execute() {
		exit;
	}
}

/**
 * A variable expression evaluates to the value of the variable
 */
class VariableExpression implements Expression {
	public function __construct($variable) {
		$this->variable = $variable;
	}
	
	public function evaluate() {
		if (isset(Basic::$variables[$this->variable])) {
			return Basic::$variables[$this->variable];
		} else {
			return FALSE;
		}
	}
}

/**
 * A number expression evaluates to a number
 */
class NumberExpression implements Expression {
	public function __construct($number) {
		$this->number = $number;
	}
	
	public function evaluate() {
		return $this->number;
	}
}

/**
 * A string expression evaluates to a string
 */
class StringExpression implements Expression {
	public function __construct($string) {
		$this->string = $string;
	}
	
	public function evaluate() {
		return $this->string;
	}
}

/**
 * An operator expression evaluates two expressions and then operates
 * on them.
 */
class OperatorExpression implements Expression {
	public function __construct($left, $operator, $right) {
		$this->left = $left;
		$this->operator = $operator;
		$this->right = $right;
	}
	
	public function evaluate() {
		$left = $this->left->evaluate();
		$right = $this->right->evaluate();
		
		switch ($this->operator) {
			case '=':
				if (is_string($left)) {
					return (bool)($left == (string)$right);
				} else {
					return (bool)($left == (int)$right);
				}
				break;
			
			case '+':
				if (is_string($left)) {
					return $left .= (string)$right;
				} else {
					return $left + (int)$right;
				}
				break;
				
			case '-':
				return $left - $right;
				break;
			
			case '*':
				return $left * $right;
				break;
				
			case '/':
				return $left / $right;
				break;
				
			case '<':
				return (bool)($left < $right);
				break;
			
			case '>':
				return (bool)($left > $right);
				break;
		}
		
		throw new BasicParserException("Unknown operator '".$this->operator."'");
	}
}

/**
 * A basic parser exception class
 **/
class BasicParserException extends Exception { }

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