<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class UserAnswerArchive extends Model
	{
		use HasFactory;

		protected $fillable = [
			'original_user_answer_id',
			'question_id',
			'subject_id',
			'selected_answer_index',
			'was_correct',
			'attempt_number',
			'archived_at',
			'archive_batch_id',
			'created_at', // Preserve original timestamp
			'updated_at', // Preserve original timestamp
		];

		protected $casts = [
			'was_correct' => 'boolean',
			'archived_at' => 'datetime',
		];

		// Optional: Turn off Laravel's default timestamp handling if you're preserving originals
		// public $timestamps = false;

		public function question()
		{
			return $this->belongsTo(Question::class);
		}

		public function subject()
		{
			return $this->belongsTo(Subject::class);
		}
	}
