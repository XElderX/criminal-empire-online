<?php

namespace App\Services;

final class OutcomePayloadService
{
    public function action(
        string $source,
        string $title,
        string $message,
        string $type = 'info',
        string $priority = 'medium',
        array $changes = [],
        array $nextActions = [],
        array $badges = []
    ): array {
        return [
            'type' => $type,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'source' => $source,
            'badges' => $badges !== [] ? $badges : $this->badgesFromChanges($changes),
            'sections' => $changes === [] ? [] : [[
                'title' => 'What changed',
                'lines' => $this->changeLines($changes),
            ]],
            'next_actions' => $nextActions,
        ];
    }

    public function crime(string $title, array $runOrResult, string $message = ''): array
    {
        $outcome = (string) ($runOrResult['outcome'] ?? ($runOrResult['success'] ?? false ? 'success' : 'failed'));
        $heat = (int) ($runOrResult['heat_gained'] ?? 0);
        $cash = (int) ($runOrResult['cash_reward'] ?? $runOrResult['reward'] ?? 0);
        $dirty = (int) ($runOrResult['dirty_cash_reward'] ?? 0);
        $xp = (int) ($runOrResult['experience_gained'] ?? 0);
        $priority = str_contains($outcome, 'critical') || $heat >= 8 ? 'critical' : 'high';

        return $this->action(
            'Crimes',
            $title . ' — ' . $this->humanize($outcome),
            $message !== '' ? $message : ($runOrResult['result_text'] ?? 'Crime result resolved.'),
            str_contains($outcome, 'fail') ? 'danger' : 'reward',
            $priority,
            ['cash' => $cash, 'dirty_money' => $dirty, 'xp' => $xp, 'heat' => $heat],
            [
                ['label' => 'Check heat', 'description' => 'Open Heat & Police if pressure climbed.'],
                ['label' => 'Manage loot', 'description' => 'Sell loot at a fence or store useful gear.'],
            ]
        );
    }

    public function dirtyJob(array $result): array
    {
        $outcome = (string) ($result['outcome'] ?? $result['status'] ?? 'resolved');
        return $this->action(
            'Dirty Jobs',
            'Dirty Job — ' . $this->humanize($outcome),
            (string) ($result['result_text'] ?? $result['message'] ?? 'Dirty Job resolved.'),
            str_contains($outcome, 'fail') ? 'danger' : 'reward',
            str_contains($outcome, 'critical') ? 'critical' : 'high',
            [
                'cash' => (int) ($result['cash_reward'] ?? 0),
                'dirty_money' => (int) ($result['dirty_cash_reward'] ?? 0),
                'xp' => (int) ($result['experience_gained'] ?? 0),
                'heat' => (int) ($result['heat_gained'] ?? 0),
            ],
            [['label' => 'Review crew', 'description' => 'Check injuries, heat, equipment damage, and loadouts.']]
        );
    }

    public function travel(array $travel): array
    {
        $event = is_array($travel['event'] ?? null) ? $travel['event'] : [];
        $title = $event !== [] ? (string) ($event['title'] ?? 'Travel Event') : 'Travel Complete';
        return $this->action(
            'World Map',
            $title,
            (string) ($event['description'] ?? $travel['message'] ?? 'Travel complete.'),
            (int) ($travel['heatChange'] ?? 0) > 0 ? 'heat' : 'travel',
            $event !== [] ? 'high' : 'medium',
            [
                'cash' => -1 * (int) ($travel['costs']['cash'] ?? 0),
                'energy' => -1 * (int) ($travel['costs']['energy'] ?? 0),
                'heat' => (int) ($travel['heatChange'] ?? 0),
            ],
            [['label' => 'View local actions', 'description' => 'Open the hotspot panel for nearby shops, jobs, crimes, and exploration.']]
        );
    }

    private function badgesFromChanges(array $changes): array
    {
        $badges = [];
        foreach ($changes as $key => $value) {
            $value = (int) $value;
            if ($value === 0) {
                continue;
            }
            $badges[] = [
                'label' => $this->humanize((string) $key),
                'value' => ($value > 0 ? '+' : '') . (string) $value,
                'kind' => $key === 'heat' ? 'heat' : ($value > 0 ? 'reward' : 'warning'),
            ];
        }
        return $badges;
    }

    private function changeLines(array $changes): array
    {
        $lines = [];
        foreach ($changes as $key => $value) {
            $value = (int) $value;
            if ($value === 0) {
                continue;
            }
            $prefix = match ($key) {
                'cash' => $value > 0 ? '+$' : '-$',
                'dirty_money' => $value > 0 ? '+$' : '-$',
                default => $value > 0 ? '+' : '',
            };
            $display = in_array($key, ['cash', 'dirty_money'], true) ? abs($value) : $value;
            $lines[] = $this->humanize((string) $key) . ': ' . $prefix . $display;
        }
        return $lines;
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
