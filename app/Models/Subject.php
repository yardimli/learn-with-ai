<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Storage; // Needed for accessor

	class Subject extends Model
	{
		use HasFactory;

		protected $fillable = [
			'name',
			'title',
			'main_text',
			'image_prompt_idea',
			'generated_image_id',
			'initial_video_job_id',
			'initial_video_path',
			'initial_video_url', // Consider removing if always derived
			'session_id',
		];

		public function generatedImage()
		{
			return $this->belongsTo(GeneratedImage::class, 'generated_image_id');
		}

		public function quizzes()
		{
			return $this->hasMany(Quiz::class);
		}

		public function userAnswers()
		{
			return $this->hasMany(UserAnswer::class);
		}
	}
