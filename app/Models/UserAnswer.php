<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class UserAnswer extends Model
	{
		use HasFactory;

		protected $fillable = [
			'question_id',
			'lesson_id',
			'selected_answer_index',
			'was_correct',
			'attempt_number',
		];

		protected $casts = [
			'was_correct' => 'boolean',
		];

		public function question()
		{
			return $this->belongsTo(Question::class);
		}

		public function lesson()
		{
			return $this->belongsTo(Lesson::class);
		}
	}
