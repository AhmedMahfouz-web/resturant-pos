<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'communication_type',
        'subject',
        'message',
        'communication_date',
        'method',
        'initiated_by',
        'response_received',
        'response_date',
        'response_time_hours',
        'satisfaction_rating',
        'notes'
    ];

    protected $casts = [
        'communication_date' => 'datetime',
        'response_date' => 'datetime',
        'response_received' => 'boolean',
        'response_time_hours' => 'decimal:1',
        'satisfaction_rating' => 'decimal:1'
    ];

    // Communication types
    const TYPE_INQUIRY = 'inquiry';
    const TYPE_ORDER = 'order';
    const TYPE_COMPLAINT = 'complaint';
    const TYPE_FEEDBACK = 'feedback';
    const TYPE_NEGOTIATION = 'negotiation';
    const TYPE_GENERAL = 'general';

    const TYPES = [
        self::TYPE_INQUIRY,
        self::TYPE_ORDER,
        self::TYPE_COMPLAINT,
        self::TYPE_FEEDBACK,
        self::TYPE_NEGOTIATION,
        self::TYPE_GENERAL
    ];

    // Communication methods
    const METHOD_EMAIL = 'email';
    const METHOD_PHONE = 'phone';
    const METHOD_SMS = 'sms';
    const METHOD_IN_PERSON = 'in_person';
    const METHOD_ONLINE_CHAT = 'online_chat';

    const METHODS = [
        self::METHOD_EMAIL,
        self::METHOD_PHONE,
        self::METHOD_SMS,
        self::METHOD_IN_PERSON,
        self::METHOD_ONLINE_CHAT
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('communication_type', $type);
    }

    public function scopeWithResponse($query)
    {
        return $query->where('response_received', true);
    }

    public function scopeWithoutResponse($query)
    {
        return $query->where('response_received', false);
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('method', $method);
    }

    // Accessors & Mutators
    public function getResponseStatusAttribute()
    {
        if (!$this->response_received) {
            return 'pending';
        }

        if ($this->response_time_hours <= 24) {
            return 'excellent';
        } elseif ($this->response_time_hours <= 48) {
            return 'good';
        } elseif ($this->response_time_hours <= 72) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    public function getFormattedResponseTimeAttribute()
    {
        if (!$this->response_time_hours) {
            return 'No response';
        }

        if ($this->response_time_hours < 1) {
            return round($this->response_time_hours * 60) . ' minutes';
        } elseif ($this->response_time_hours < 24) {
            return round($this->response_time_hours, 1) . ' hours';
        } else {
            return round($this->response_time_hours / 24, 1) . ' days';
        }
    }

    // Methods
    public function markResponseReceived($satisfactionRating = null)
    {
        $responseTime = $this->communication_date->diffInHours(now());

        $this->update([
            'response_received' => true,
            'response_date' => now(),
            'response_time_hours' => $responseTime,
            'satisfaction_rating' => $satisfactionRating
        ]);
    }

    public static function getAverageResponseTime($supplierId, $days = 30)
    {
        return static::where('supplier_id', $supplierId)
            ->where('response_received', true)
            ->where('communication_date', '>=', now()->subDays($days))
            ->avg('response_time_hours') ?? 0;
    }

    public static function getResponseRate($supplierId, $days = 30)
    {
        $total = static::where('supplier_id', $supplierId)
            ->where('communication_date', '>=', now()->subDays($days))
            ->count();

        if ($total === 0) return 100;

        $responded = static::where('supplier_id', $supplierId)
            ->where('response_received', true)
            ->where('communication_date', '>=', now()->subDays($days))
            ->count();

        return ($responded / $total) * 100;
    }
}
