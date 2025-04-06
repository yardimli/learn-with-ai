<?php

	namespace App\Http\Controllers;

	use App\Models\GeneratedImage;
	use App\Models\Quiz;
	use Exception;
	use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
	use Illuminate\Foundation\Validation\ValidatesRequests;
	use Illuminate\Http\UploadedFile;
	use Illuminate\Routing\Controller as BaseController;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;
	use Intervention\Image\ImageManager;
	use Intervention\Image\Drivers\Gd\Driver;


	class Controller extends BaseController
	{
		use AuthorizesRequests, ValidatesRequests;

	}
