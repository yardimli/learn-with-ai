<?php

	namespace App\Http\Controllers;

	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;

	class UserController extends Controller
	{
		/**
		 * Get the current user's saved LLM generation instructions.
		 *
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function getLlmInstructions()
		{
			$user = Auth::user();
			return response()->json([
				'success' => true,
				'instructions' => $user->llm_generation_instructions ?? ''
			]);
		}
	}
