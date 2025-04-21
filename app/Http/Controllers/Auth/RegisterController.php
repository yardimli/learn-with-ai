<?php

	namespace App\Http\Controllers\Auth;

	use App\Http\Controllers\Controller;
	use App\Models\User;
	use Illuminate\Foundation\Auth\RegistersUsers;
	use Illuminate\Support\Facades\Hash;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Http\Request; // Import Request
	use Illuminate\Support\Facades\Session; // Import Session facade or use session() helper

	class RegisterController extends Controller
	{
		/*
		|--------------------------------------------------------------------------
		| Register Controller
		|--------------------------------------------------------------------------
		|
		| This controller handles the registration of new users as well as their
		| validation and creation. By default this controller uses a trait to
		| provide this functionality without requiring any additional code.
		|
		*/

		use RegistersUsers;

		/**
		 * Where to redirect users after registration.
		 *
		 * @var string
		 */
		protected $redirectTo = '/home';

		/**
		 * Create a new controller instance.
		 *
		 * @return void
		 */
		public function __construct()
		{
			$this->middleware('guest');
		}

		/**
		 * Show the application registration form.
		 * Overrides the trait method to add math captcha data.
		 *
		 * @return \Illuminate\View\View
		 */
		public function showRegistrationForm()
		{
			// Generate math problem
			$num1 = rand(111, 312);
			$num2 = rand(111, 442);
			$mathQuestion = "What is $num1 + $num2?";
			$correctAnswer = $num1 + $num2;

			// Store the correct answer in the session
			session(['math_captcha_answer' => $correctAnswer]); // Using session() helper

			// Pass the question to the view
			return view('auth.register', ['math_question' => $mathQuestion]);
		}


		/**
		 * Get a validator for an incoming registration request.
		 *
		 * @param  array  $data
		 * @return \Illuminate\Contracts\Validation\Validator
		 */
		protected function validator(array $data)
		{
			return Validator::make($data, [
				'name' => ['required', 'string', 'max:255'],
				'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
				'password' => ['required', 'string', 'min:8', 'confirmed'],
				// Add validation for the math captcha
				'math_captcha' => [
					'required',
					'numeric',
					function ($attribute, $value, $fail) {
						// Retrieve the correct answer from the session
						$correctAnswer = session('math_captcha_answer');

						// IMPORTANT: Forget the session value AFTER checking it.
						// This prevents using the same answer multiple times if validation fails elsewhere.
						// However, showRegistrationForm will generate a new one on page reload anyway.
						// session()->forget('math_captcha_answer'); // Optional: uncomment if you want only one check per page load

						if ($value != $correctAnswer) {
							// Use a generic message for security, or a specific one for debugging
							$fail('The math problem answer is incorrect.');
							// $fail("The answer to the math problem was incorrect. Expected {$correctAnswer}, got {$value}");
						}
					},
				],
			]);
		}

		/**
		 * Create a new user instance after a valid registration.
		 *
		 * @param  array  $data
		 * @return \App\Models\User
		 */
		protected function create(array $data)
		{
			// Clear the captcha answer from session upon successful creation
			session()->forget('math_captcha_answer');

			return User::create([
				'name' => $data['name'],
				'email' => $data['email'],
				'password' => Hash::make($data['password']),
			]);
		}
	}
