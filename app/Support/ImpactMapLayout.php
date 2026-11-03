<?php

namespace App\Support;

use App\Models\ImpactMapNode;
use Illuminate\Support\Collection;

/**
 * Left-to-right tree layout: each leaf gets one row; a parent sits centred on its children.
 */
class ImpactMapLayout
{
    /** Minimum vertical pitch per leaf. */
    public const ROW = 64;

    /** Clear space between stacked boxes (added to each box's own height). */
    public const GAP = 18;

    /** Extra space between sibling groups (non-story siblings, e.g. epics/impacts). */
    public const GROUP_GAP = 14;

    public const TOP = 100;

    /** Left edge and box width per level. */
    public const COLUMNS = [
        [20, 220],
        [300, 170],
        [530, 180],
        [770, 180],
        [1000, 160],
    ];

    /** Font size per level (mirrors the CSS); used to estimate wrapped box height. */
    private const FONT = [24, 18, 16, 16, 14];

    /**
     * Each leaf gets a slot of max(ROW, box height + GAP); a parent sits centred on its children and
     * its children block is padded when the parent box itself is taller. The browser re-runs the same
     * rule with measured heights (show.blade.php), this server pass uses an estimate.
     *
     * @param  Collection<int, ImpactMapNode>  $nodes
     * @return array{boxes: list<array<string, mixed>>, links: list<array<string, float>>, width: int, height: int}
     */
    public static function compute(Collection $nodes): array
    {
        $byParent = $nodes->sortBy([['position', 'asc'], ['id', 'asc']])->groupBy(fn ($n) => $n->parent_id ?? 0);
        $root = $byParent->get(0)?->first();
        $boxes = [];
        $links = [];
        $ys = [];
        $cur = (float) self::TOP;

        if ($root !== null) {
            self::place($root, $byParent, $cur, $ys);
            self::collect($root, $byParent, $ys, $boxes, $links);
        }

        $maxLevel = collect($boxes)->max('level') ?? 0;
        [$x, $w] = self::COLUMNS[min(ImpactMapNode::STORY, $maxLevel + 1)];

        return [
            'boxes' => $boxes,
            'links' => $links,
            'width' => max(1200, $x + $w + 40),
            'height' => (int) ceil(max($cur, self::TOP + self::ROW) + 40),
        ];
    }

    public static function estimateHeight(int $level, string $title): float
    {
        $level = min($level, ImpactMapNode::STORY);
        $fs = self::FONT[$level];
        $padX = $level === 0 ? 28 : 20;
        $padY = $level === 0 ? 28 : 12;
        $per = max(4, (int) floor((self::COLUMNS[$level][1] - $padX) / ($fs * 0.6)));
        $lines = 1;
        $len = 0;
        foreach (preg_split('/\s+/u', trim($title)) ?: [] as $word) {
            $l = mb_strlen($word);
            if ($l === 0) {
                continue;
            }
            if ($len === 0) {
                $len = $l;
            } elseif ($len + 1 + $l <= $per) {
                $len += 1 + $l;
            } else {
                $lines++;
                $len = $l;
            }
            while ($len > $per) {
                $lines++;
                $len -= $per;
            }
        }
        $h = $padY + $lines * $fs * 1.1;

        return $level === 0 ? max(90.0, $h) : $h;
    }

    private static function place(ImpactMapNode $node, Collection $byParent, float &$cur, array &$ys): float
    {
        $children = $byParent->get($node->id, collect())->values();
        $need = self::estimateHeight((int) $node->level, (string) $node->title) + self::GAP;
        $start = $cur;

        if ($children->isEmpty()) {
            $span = max(self::ROW, $need);
            $y = $cur + $span / 2;
            $cur += $span;
        } else {
            $c = [];
            foreach ($children as $i => $child) {
                if ($i > 0 && min($child->level, ImpactMapNode::STORY) < ImpactMapNode::STORY) {
                    $cur += self::GROUP_GAP;
                }
                $c[] = self::place($child, $byParent, $cur, $ys);
            }
            $y = (min($c) + max($c)) / 2;
            if ($cur - $start < $need) {
                $pad = ($need - ($cur - $start)) / 2;
                foreach ($children as $child) {
                    self::shift($child, $byParent, $pad, $ys);
                }
                $y += $pad;
                $cur = $start + $need;
            }
        }

        return $ys[$node->id] = $y;
    }

    private static function shift(ImpactMapNode $node, Collection $byParent, float $dy, array &$ys): void
    {
        $ys[$node->id] += $dy;
        foreach ($byParent->get($node->id, collect()) as $child) {
            self::shift($child, $byParent, $dy, $ys);
        }
    }

    private static function collect(ImpactMapNode $node, Collection $byParent, array $ys, array &$boxes, array &$links): void
    {
        $level = min($node->level, ImpactMapNode::STORY);
        [$x, $w] = self::COLUMNS[$level];
        foreach ($byParent->get($node->id, collect()) as $child) {
            self::collect($child, $byParent, $ys, $boxes, $links);
            $links[] = ['x1' => $x + $w, 'y1' => $ys[$node->id], 'x2' => self::COLUMNS[min($level + 1, ImpactMapNode::STORY)][0], 'y2' => $ys[$child->id]];
        }
        $boxes[] = ['node' => $node, 'level' => $level, 'x' => $x, 'y' => $ys[$node->id], 'w' => $w];
    }
}
