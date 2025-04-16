<?php

	namespace App\Helpers;

	use App\Models\Lesson;
	use App\Models\Question;
	use App\Models\GeneratedImage;
	use App\Models\UserAnswer;
	use Carbon\Carbon;
	use GuzzleHttp\Client;
	use Illuminate\Http\Request;
	use Illuminate\Http\UploadedFile;
	use Illuminate\Support\Facades\Auth;

	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;

	use Ahc\Json\Fixer;
	use Illuminate\Support\Str;

	use Google\Cloud\TextToSpeech\V1\AudioConfig;
	use Google\Cloud\TextToSpeech\V1\AudioEncoding;
	use Google\Cloud\TextToSpeech\V1\SsmlVoiceGender;
	use Google\Cloud\TextToSpeech\V1\SynthesisInput;
	use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
	use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
	use Exception;
	use Intervention\Image\ImageManager;
	use Normalizer;


	class LlmHelper
	{
		public static function checkLLMsJson()
		{
			// Ensure Storage facade is correctly used
			$llmsJsonPath = storage_path('app/public/llms.json'); // Store in storage/app/public

			// Create public directory if it doesn't exist
			if (!File::exists(storage_path('app/public'))) {
				Storage::disk('public')->makeDirectory('/');
			}


			if (!File::exists($llmsJsonPath) || Carbon::now()->diffInDays(Carbon::createFromTimestamp(File::lastModified($llmsJsonPath))) > 1) {
				try {
					$client = new Client(['timeout' => 30]); // Add timeout
					$response = $client->get('https://openrouter.ai/api/v1/models');
					$data = json_decode($response->getBody(), true);

					if (isset($data['data'])) {
						File::put($llmsJsonPath, json_encode($data['data'], JSON_PRETTY_PRINT)); // Make it readable
					} else {
						Log::warning('Failed to fetch or parse LLMs from OpenRouter.');
						// Fallback: Check if an older file exists, otherwise return empty
						return File::exists($llmsJsonPath) ? json_decode(File::get($llmsJsonPath), true) : [];
					}
				} catch (\Exception $e) {
					Log::error('Error fetching LLMs from OpenRouter: ' . $e->getMessage());
					// Fallback: Check if an older file exists, otherwise return empty
					return File::exists($llmsJsonPath) ? json_decode(File::get($llmsJsonPath), true) : [];
				}
			}

			// --- Modification for no-login ---
			// For this app, we always act as if the user has *no* key initially.
			// We could potentially allow an admin key via env for wider model access internally.
			// $openrouter_admin_or_key = false;
			// if ((Auth::user() && Auth::user()->isAdmin()) ||
			//     (Auth::user() && !empty(Auth::user()->openrouter_key))) {
			//     $openrouter_admin_or_key = true;
			// }
			// Let's simplify: Use a flag based on whether an ADMIN key is set,
			// otherwise assume the cheaper models are desired.
			$allow_expensive_models = !empty(env('ADMIN_OPEN_ROUTER_KEY')); // Example env var

			$llms = json_decode(File::get($llmsJsonPath), true);
			if (!is_array($llms)) { // Handle case where file is corrupted
				Log::error('Failed to decode llms.json');
				return [];
			}


			$filtered_llms = array_filter($llms, function ($llm) use ($allow_expensive_models) {
				if (!isset($llm['id'])) return false; // Skip if no ID

				// --- Your existing filters ---
				if (stripos($llm['id'], 'openrouter/auto') !== false) return false;
				if (stripos($llm['id'], 'vision') !== false) return false; // Keep vision filter
				if (stripos($llm['id'], '-3b-') !== false) return false;
				if (stripos($llm['id'], '-1b-') !== false) return false;
				if (stripos($llm['id'], 'online') !== false) return false;
				if (stripos($llm['id'], 'gpt-3.5') !== false) return false;
				// Add other models to explicitly exclude if needed
				if (in_array($llm['id'], ['google/gemma-7b-it', 'huggingfaceh4/zephyr-7b-beta'])) {
					return false;
				}


				// --- Price filter ---
				if (isset($llm['pricing']['completion'])) {
					// Normalize pricing - OpenRouter gives price per 1k tokens, needs conversion to per 1M
					$price_per_million = 0;
					// Handle potential string or numeric values robustly
					try {
						// Remove '$' if present and convert to float
						$completion_price_str = str_replace('$', '', (string)$llm['pricing']['completion']);
						$price_per_thousand = floatval($completion_price_str);
						$price_per_million = $price_per_thousand * 1000;
					} catch (\Exception $e) {
						Log::warning("Could not parse price for LLM {$llm['id']}: " . $e->getMessage());
						return false; // Exclude if price parsing fails
					}


					if ($allow_expensive_models) {
						// Allow more expensive models if admin key might be used (e.g., up to $20/M)
						return $price_per_million <= 20.0;
					} else {
						// Stricter limit for general use (e.g., up to $1.5/M)
						return $price_per_million <= 1.5;
					}
				}

				// Exclude if no completion pricing is available
				return false;
			});

			usort($filtered_llms, function ($a, $b) {
				$nameA = $a['name'] ?? '';
				$nameB = $b['name'] ?? '';
				return strcmp($nameA, $nameB);
			});

			// Return array values to reset keys
			return array_values($filtered_llms);
		}

		public static function validateJson($str)
		{
			// Minor improvement: Check if it's potentially empty or not a string first
			if (empty($str) || !is_string($str)) {
				return "Input is empty or not a string";
			}

			// Attempt to decode
			json_decode($str);

			// Check for errors
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					return "Valid JSON"; // Success
				case JSON_ERROR_DEPTH:
					return "Maximum stack depth exceeded";
				case JSON_ERROR_STATE_MISMATCH:
					return "Underflow or the modes mismatch";
				case JSON_ERROR_CTRL_CHAR:
					return "Unexpected control character found";
				case JSON_ERROR_SYNTAX:
					return "Syntax error, malformed JSON";
				case JSON_ERROR_UTF8:
					return "Malformed UTF-8 characters, possibly incorrectly encoded";
				default:
					return "Unknown JSON error";
			}
		}

		public static function repaceNewLineWithBRInsideQuotes($input)
		{
			// If input is not a string, return it as is
			if (!is_string($input)) {
				return $input;
			}

			$output = '';
			$inQuotes = false;
			$length = strlen($input);
			$i = 0;

			while ($i < $length) {
				$char = $input[$i];
				$escaped = false;

				// Check for preceding backslash to handle escaped quotes
				if ($i > 0 && $input[$i - 1] === '\\') {
					// Count consecutive backslashes ending at i-1
					$backslashCount = 0;
					for ($j = $i - 1; $j >= 0; $j--) {
						if ($input[$j] === '\\') {
							$backslashCount++;
						} else {
							break;
						}
					}
					// If odd number of backslashes, the quote is escaped
					if ($backslashCount % 2 !== 0) {
						$escaped = true;
					}
				}


				if ($char === '"' && !$escaped) {
					$inQuotes = !$inQuotes;
					$output .= $char;
				} elseif ($inQuotes) {
					// Handle \n, \r, \r\n within quotes
					if ($char === '\\' && $i + 1 < $length) {
						$nextChar = $input[$i + 1];
						if ($nextChar === 'n') { // Check for literal \n
							$output .= '<BR>'; // Replace with <BR>
							$i++; // Skip the 'n'
						} elseif ($nextChar === 'r') { // Check for literal \r
							$output .= '<BR>'; // Replace with <BR>
							$i++; // Skip the 'r'
							// Check for subsequent \n (for \r\n)
							if ($i + 1 < $length && $input[$i + 1] === '\\' && $i + 2 < $length && $input[$i + 2] === 'n') {
								$i += 2; // Skip the '\' and 'n'
							}
						} else {
							// Not an escaped newline, append the backslash and the next character
							$output .= $char . $nextChar;
							$i++;
						}
					} elseif (($char === "\n" || $char === "\r") && !$escaped) { // Handle actual newline characters
						$output .= '<BR>';
						// Handle Windows CRLF (\r\n) - skip the \n if preceded by \r
						if ($char === "\r" && $i + 1 < $length && $input[$i + 1] === "\n") {
							$i++;
						}
					} else {
						$output .= $char; // Append other characters within quotes
					}
				} else {
					$output .= $char; // Append characters outside quotes
				}
				$i++;
			}

			return $output;
		}

		public static function getContentsInBackticksOrOriginal($input)
		{
			// Handle non-string input
			if (!is_string($input)) {
				return $input;
			}

			// Regular expression to find content within triple backticks (common for code blocks)
			// or single backticks. Captures content *without* the backticks.
			// It prioritizes triple backticks if found.
			$triplePattern = '/```(?:json)?\s*([\s\S]*?)\s*```/'; // Handles optional json marker
			$singlePattern = '/`([^`]+)`/';

			$matches = [];

			// First, try to match triple backticks
			if (preg_match($triplePattern, $input, $matches)) {
				// Return the content of the first capture group, trimmed
				return trim($matches[1]);
			}

			// If no triple backticks, try single backticks (find all occurrences)
			if (preg_match_all($singlePattern, $input, $matches)) {
				// Join all single-backtick matches, trimmed
				return trim(implode(' ', $matches[1]));
			}

			// If no backticks are found, return the original input, trimmed
			return trim($input);
		}

		public static function extractJsonString($input)
		{
			// Handle non-string input
			if (!is_string($input)) {
				return ''; // Return empty string if not a string
			}

			// Find the first occurrence of '{' or '['
			$firstCurly = strpos($input, '{');
			$firstSquare = strpos($input, '[');

			// Determine the starting position and type
			$startPos = false;
			$startChar = '';
			if ($firstCurly !== false && ($firstSquare === false || $firstCurly < $firstSquare)) {
				$startPos = $firstCurly;
				$startChar = '{';
				$endChar = '}';
			} elseif ($firstSquare !== false) {
				$startPos = $firstSquare;
				$startChar = '[';
				$endChar = ']';
			}

			// If no starting bracket is found, return empty string
			if ($startPos === false) {
				return '';
			}

			// Find the last corresponding closing bracket, considering nesting
			$openCount = 0;
			$endPos = -1;
			$inString = false;
			$escaped = false;
			$len = strlen($input);

			for ($i = $startPos; $i < $len; $i++) {
				$char = $input[$i];

				// Toggle inString state, handling escaped quotes
				if ($char === '"' && !$escaped) {
					$inString = !$inString;
				}

				// Track escape character status (only outside strings matters for brackets)
				$escaped = (!$inString && $char === '\\' && !$escaped);


				if (!$inString) {
					if ($char === $startChar) {
						$openCount++;
					} elseif ($char === $endChar) {
						$openCount--;
					}

					if ($openCount === 0) {
						$endPos = $i;
						break; // Found the matching end bracket
					}
				} else {
					// Reset escaped flag if not a backslash inside a string
					if ($char !== '\\') $escaped = false;
				}
			}


			// If a matching end bracket was found
			if ($endPos !== -1) {
				// Extract the substring from start position to end position inclusive
				return substr($input, $startPos, $endPos - $startPos + 1);
			}

			// If no matching end bracket found (e.g., truncated JSON), return empty or partial?
			// Returning empty is safer for preventing parsing errors later.
			return '';
		}

		public static function mergeStringsWithoutRepetition($string1, $string2, $maxRepetitionLength = 100)
		{
			// Handle non-string inputs
			if (!is_string($string1) || !is_string($string2)) {
				Log::warning("mergeStringsWithoutRepetition received non-string input.");
				// Decide on behavior: return first string, empty, or throw error?
				return (string)$string1 . (string)$string2; // Simple concatenation as fallback
			}


			$len1 = strlen($string1);
			$len2 = strlen($string2);

			// Ensure maxRepetitionLength is not negative
			$maxRepetitionLength = max(0, $maxRepetitionLength);


			// Determine the maximum possible overlap length
			$maxPossibleOverlap = min($maxRepetitionLength, $len1, $len2);

			// Find the length of the longest suffix of string1 that is a prefix of string2
			$overlapLength = 0;
			for ($length = $maxPossibleOverlap; $length >= 1; $length--) {
				if (substr($string1, -$length) === substr($string2, 0, $length)) {
					$overlapLength = $length;
					break; // Found the longest overlap
				}
			}

			// Append the non-overlapping part of string2
			return $string1 . substr($string2, $overlapLength);
		}

		public static function getOpenRouterKey()
		{
			// Prioritize Admin key if set, otherwise use the general key
			return env('ADMIN_OPEN_ROUTER_KEY', env('OPEN_ROUTER_KEY'));
		}

		public static function splitIntoSentences(string $text): array {
			if (empty($text)) {
				return [];
			}

			// Normalize Unicode characters to their canonical form
			$text = Normalizer::normalize($text, Normalizer::FORM_C);

			// Normalize line breaks and multiple spaces
			$text = preg_replace('/\r\n|\r|\n/', ' ', $text);
			$text = preg_replace('/\s+/', ' ', $text);

			// Common abbreviations to avoid splitting on
			$abbreviations = [
				'Mr', 'Mrs', 'Ms', 'Dr', 'Prof', 'Rev', 'Sr', 'Jr', 'St',
				'e\.g', 'i\.e', 'etc', 'vs', 'No'
			];
			$abbrevPattern = implode('|', $abbreviations);

			// Process the text in a way that avoids variable-length lookbehinds
			$processedText = $text . ' '; // Add trailing space

			// First mark potential sentence boundaries
			$processedText = preg_replace('/([.!?])(["\'"\']?)\s+/', '$1$2<--SENTENCE_END-->', $processedText);

			// Now remove markers after common abbreviations
			foreach ($abbreviations as $abbrev) {
				$processedText = preg_replace('/' . preg_quote($abbrev, '/') . '\.<--SENTENCE_END-->/', $abbrev . '.', $processedText);
			}

			// Don't split on decimal numbers
			$processedText = preg_replace('/(\d)\.\d+<--SENTENCE_END-->/', '$1.', $processedText);

			// Don't split on website domains and similar patterns
			$processedText = preg_replace('/(\w)\.\w+<--SENTENCE_END-->/', '$1.', $processedText);

			// Split the text using the marker
			$sentences = explode('<--SENTENCE_END-->', $processedText);

			// Trim whitespace and filter out empty sentences
			return array_values(array_filter(array_map('trim', $sentences)));
		}

		public static function llm_no_tool_call($llm, $system_prompt, $chat_messages, $return_json = true, $max_retries = 1)
		{
			set_time_limit(300);
			session_write_close();

			$llm_base_url = env('OPEN_ROUTER_BASE', 'https://openrouter.ai/api/v1/chat/completions');
			$llm_api_key = self::getOpenRouterKey();
			$llm_model = $llm ?? '';
			if ($llm_model === '') {
				$llm_model = env('DEFAULT_LLM');
			}

			if (empty($llm_api_key)) {
				Log::error("OpenRouter API Key is not configured.");
				return $return_json ? ['error' => 'API key not configured'] : ['content' => 'Error: API key not configured', 'prompt_tokens' => 0, 'completion_tokens' => 0];
			}

			$all_messages = [];
			$all_messages[] = ['role' => 'system', 'content' => $system_prompt];
			$all_messages = array_merge($all_messages, $chat_messages);

			if (empty($all_messages)) {
				Log::warning("LLM call attempted with no messages.");
				return $return_json ? ['error' => 'No messages provided'] : ['content' => 'Error: No messages provided', 'prompt_tokens' => 0, 'completion_tokens' => 0];
			}

			$temperature = 0.7; // Slightly lower temp for more predictable JSON
			$max_tokens = 8192; // Adjust based on model/needs

			$data = [
				'model' => $llm_model,
				'messages' => $all_messages,
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
				'stream' => false,
			];

			Log::info("LLM Request to {$llm_base_url} ({$llm_model})");
			Log::debug("LLM Request Data: ", $data);

			$attempt = 0;
			$content = null;
			$prompt_tokens = 0;
			$completion_tokens = 0;
			$last_error = null;

			while ($attempt <= $max_retries && $content === null) {
				$attempt++;
				Log::info("LLM Call Attempt: {$attempt}");
				try {
					$client = new Client(['timeout' => 180.0]);

					$headers = [
						'Content-Type' => 'application/json',
						'Authorization' => 'Bearer ' . $llm_api_key,
						'HTTP-Referer' => env('APP_URL', 'http://localhost'),
						'X-Title' => env('APP_NAME', 'Laravel'),
					];

					$response = $client->post($llm_base_url, [
						'headers' => $headers,
						'json' => $data,
					]);

					$responseBody = $response->getBody()->getContents();
					Log::info("LLM Response Status: " . $response->getStatusCode());
					Log::debug("LLM Raw Response Body: " . $responseBody);

					$complete_rst = json_decode($responseBody, true);

					if (json_last_error() !== JSON_ERROR_NONE) {
						Log::error("Failed to decode LLM JSON response: " . json_last_error_msg());
						Log::error("Raw response causing decoding error: " . $responseBody);
						$last_error = "Failed to decode LLM response.";
						// If retry is possible, continue loop, otherwise fail
						if ($attempt > $max_retries) {
							return $return_json ? ['error' => $last_error] : ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
						}
						sleep(2); // Wait before retry
						continue;
					}

					// Check for API errors in the response structure
					if (isset($complete_rst['error'])) {
						$error_message = $complete_rst['error']['message'] ?? json_encode($complete_rst['error']);
						Log::error("LLM API Error: " . $error_message);
						$last_error = "LLM API Error: " . $error_message;
						// If retry is possible, continue loop, otherwise fail
						if ($attempt > $max_retries) {
							return $return_json ? ['error' => $last_error] : ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
						}
						sleep(2); // Wait before retry
						continue; // Go to next attempt
					}

					// Extract content and usage based on common structures
					if (isset($complete_rst['choices'][0]['message']['content'])) { // OpenAI, Mistral, etc.
						$content = $complete_rst['choices'][0]['message']['content'];
						$prompt_tokens = $complete_rst['usage']['prompt_tokens'] ?? 0;
						$completion_tokens = $complete_rst['usage']['completion_tokens'] ?? 0;
					} elseif (isset($complete_rst['content'][0]['text'])) { // Anthropic
						$content = $complete_rst['content'][0]['text'];
						$prompt_tokens = $complete_rst['usage']['input_tokens'] ?? $complete_rst['usage']['prompt_tokens'] ?? 0; // Anthropic uses input_tokens
						$completion_tokens = $complete_rst['usage']['output_tokens'] ?? $complete_rst['usage']['completion_tokens'] ?? 0; // Anthropic uses output_tokens
					} elseif (isset($complete_rst['candidates'][0]['content']['parts'][0]['text'])) { // Google Gemini
						$content = $complete_rst['candidates'][0]['content']['parts'][0]['text'];
						// Google usage might be elsewhere or not provided by OpenRouter consistently
						$prompt_tokens = $complete_rst['usageMetadata']['promptTokenCount'] ?? 0;
						$completion_tokens = $complete_rst['usageMetadata']['candidatesTokenCount'] ?? 0;
					} else {
						Log::error("Could not find content in LLM response structure.");
						Log::debug("Full response structure: ", $complete_rst);
						$last_error = "Could not find content in LLM response.";
						// If retry is possible, continue loop, otherwise fail
						if ($attempt > $max_retries) {
							return $return_json ? ['error' => $last_error] : ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
						}
						sleep(2); // Wait before retry
						continue; // Go to next attempt
					}

					break;

				} catch (\GuzzleHttp\Exception\RequestException $e) {
					$statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
					$errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
					Log::error("Guzzle HTTP Request Exception during LLM call (Attempt {$attempt}): Status {$statusCode} - " . $errorBody);
					$last_error = "HTTP Error {$statusCode}";

					if ($attempt > $max_retries || ($statusCode >= 400 && $statusCode < 500 && $statusCode != 429)) {
						return $return_json ? ['error' => $last_error] : ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
					}
					sleep(pow(2, $attempt)); // Exponential backoff
				} catch (\Exception $e) {
					Log::error("General Exception during LLM call (Attempt {$attempt}): " . $e->getMessage());
					$last_error = "General Error: " . $e->getMessage();
					if ($attempt > $max_retries) {
						return $return_json ? ['error' => $last_error] : ['content' => "Error: {$last_error}", 'prompt_tokens' => 0, 'completion_tokens' => 0];
					}
					sleep(pow(2, $attempt)); // Exponential backoff
				}
			} // End while loop

			if ($content === null) {
				Log::error("LLM call failed after {$max_retries} retries. Last error: {$last_error}");
				return $return_json ? ['error' => $last_error ?: 'LLM call failed after retries.'] : ['content' => "Error: " . ($last_error ?: 'LLM call failed after retries.'), 'prompt_tokens' => 0, 'completion_tokens' => 0];
			}

			Log::info("LLM Success. Prompt Tokens: {$prompt_tokens}, Completion Tokens: {$completion_tokens}");
			Log::debug("Raw content from LLM: " . $content);

			if (!$return_json) {
				Log::info('Returning raw text content.');
				return ['content' => $content, 'prompt_tokens' => $prompt_tokens, 'completion_tokens' => $completion_tokens];
			}

			// --- JSON Processing ---
			Log::info('Attempting to extract and validate JSON.');
			$extracted_content = self::getContentsInBackticksOrOriginal($content); // Extract from backticks first
			$json_string = self::extractJsonString($extracted_content); // Then find the JSON structure

			if (empty($json_string)) {
				Log::warning("Could not extract a JSON structure from the LLM response.");
				Log::debug("Content after backtick removal: " . $extracted_content);
				$json_string = $extracted_content;
			}

			$json_string_processed = $json_string;
			$validate_result = self::validateJson($json_string_processed);

			if ($validate_result === "Valid JSON") {
				Log::info('JSON is valid on first pass.');
				$content_rst = json_decode($json_string_processed, true);
				$content_rst['_usage'] = ['prompt_tokens' => $prompt_tokens, 'completion_tokens' => $completion_tokens];
				return $content_rst;
			} else {
				Log::warning("Initial JSON validation failed: {$validate_result}");
				Log::debug("String failing validation: " . $json_string_processed);

				try {
					$fixer = new Fixer();
					$fixer->silent(true)->missingValue('"<--MISSING-->"');
					$fixed_json_string = $fixer->fix($json_string_processed);

					$validate_result_fixed = self::validateJson($fixed_json_string);

					if ($validate_result_fixed === "Valid JSON") {
						Log::info('JSON successfully fixed.');
						$content_rst = json_decode($fixed_json_string, true);
						$content_rst['_usage'] = ['prompt_tokens' => $prompt_tokens, 'completion_tokens' => $completion_tokens];
						return $content_rst;
					} else {
						Log::error("JSON fixing failed. Validation after fix: {$validate_result_fixed}");
						Log::debug("String after attempting fix: " . $fixed_json_string);
						// Return error if fixing fails
						return ['error' => 'Failed to parse or fix JSON response', 'details' => $validate_result_fixed, '_usage' => ['prompt_tokens' => $prompt_tokens, 'completion_tokens' => $completion_tokens]];
					}
				} catch (\Exception $e) {
					Log::error("Exception during JSON fixing: " . $e->getMessage());
					return ['error' => 'Exception during JSON fixing', 'details' => $e->getMessage(), '_usage' => ['prompt_tokens' => $prompt_tokens, 'completion_tokens' => $completion_tokens]];
				}
			}
		}

		// --- End of LlmHelper class ---
	}
