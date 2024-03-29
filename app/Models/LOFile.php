<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property mixed $id
 * @property mixed $mime_type
 * @property mixed $type
 * @property mixed $filename
 */
class LOFile extends Model
{
    protected $guarded = ['id'];
    public    $table   = "lo_files";


    /**
     * A file can belong to a category.
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FileCategory::class, 'file_category_id');
    }

}
