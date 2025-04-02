<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class UserAnswer extends Model
	{
		use HasFactory;

		protected $fillable = [
			'quiz_id',
			'subject_id',
			'selected_answer_index',
			'was_correct',
			'attempt_number',
		];

		protected $casts = [
			'was_correct' => 'boolean',
		];

		public function quiz()
		{
			return $this->belongsTo(Quiz::class);
		}

		public function subject()
		{
			return $this->belongsTo(Subject::class);
		}
	}
