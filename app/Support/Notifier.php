<?php

namespace App\Support;

use App\Models\Contribution;
use App\Models\Goal;
use App\Models\Notification;
use App\Models\User;

/**
 * Creates in-app notifications for pair activity.
 *
 * All partner-facing events are skipped for solo pairs (no partner). The only
 * notification a solo user receives is "goal achieved" (to themselves).
 */
class Notifier
{
    /**
     * Goal proposed -> notify the partner (not the proposer).
     */
    public static function goalProposed(Goal $goal): void
    {
        $goal->loadMissing('pair', 'proposedBy');

        if ($goal->pair->isSolo() || ! $goal->proposedBy) {
            return;
        }

        $recipient = $goal->pair->partnerOf($goal->proposedBy);

        if ($recipient) {
            self::store($recipient->id, 'goal_proposed', 'Usulan goal baru',
                $goal->proposedBy->name.' mengusulkan goal "'.$goal->name.'".', $goal);
        }
    }

    /**
     * Goal approved / rejected -> notify the proposer (not the decider).
     */
    public static function goalDecided(Goal $goal, User $decider, bool $approved): void
    {
        $goal->loadMissing('pair', 'proposedBy');

        if ($goal->pair->isSolo() || ! $goal->proposedBy || $goal->proposedBy->id === $decider->id) {
            return;
        }

        self::store(
            $goal->proposedBy->id,
            $approved ? 'goal_approved' : 'goal_rejected',
            $approved ? 'Goal disetujui' : 'Goal ditolak',
            $decider->name.' '.($approved ? 'menyetujui' : 'menolak').' goal "'.$goal->name.'".',
            $goal,
        );
    }

    /**
     * Contribution added -> notify the partner (not the contributor).
     */
    public static function contributionAdded(Goal $goal, Contribution $contribution): void
    {
        self::relayActivity($goal, $contribution, 'contribution_added', 'Kontribusi baru', 'menambah', 'ke');
    }

    /**
     * Withdrawal recorded -> notify the partner (not the person who recorded it).
     */
    public static function withdrawalAdded(Goal $goal, Contribution $contribution): void
    {
        self::relayActivity($goal, $contribution, 'withdrawal_added', 'Penarikan dana', 'menarik', 'dari');
    }

    /**
     * Goal achieved -> notify both members (or the solo user, themselves).
     */
    public static function goalAchieved(Goal $goal): void
    {
        $goal->loadMissing('pair');

        $recipients = $goal->pair->isSolo()
            ? [$goal->pair->user_one_id]
            : [$goal->pair->user_one_id, $goal->pair->user_two_id];

        foreach (array_filter($recipients) as $userId) {
            self::store($userId, 'goal_achieved', 'Goal tercapai',
                'Goal "'.$goal->name.'" telah tercapai.', $goal);
        }
    }

    private static function relayActivity(Goal $goal, Contribution $contribution, string $type, string $title, string $verb, string $preposition): void
    {
        $goal->loadMissing('pair');

        if ($goal->pair->isSolo()) {
            return;
        }

        $actor = $contribution->user ?: User::find($contribution->user_id);

        if (! $actor) {
            return;
        }

        $recipient = $goal->pair->partnerOf($actor);

        if ($recipient) {
            $amount = 'Rp '.number_format((float) $contribution->amount, 0, ',', '.');

            self::store($recipient->id, $type, $title,
                $actor->name.' '.$verb.' '.$amount.' '.$preposition.' "'.$goal->name.'".', $goal);
        }
    }

    private static function store(int $userId, string $type, string $title, string $message, Goal $goal): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => ['goal_id' => $goal->id],
        ]);
    }
}
