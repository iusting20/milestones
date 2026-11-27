<?php

namespace Tests\Feature;

use App\Livewire\Read;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadDelimiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_a_book_delimiter_and_reorder_it(): void
    {
        $book = Book::query()->create([
            'title' => 'Atomic Habits',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_BOOK,
        ]);

        Livewire::test(Read::class)
            ->assertSee('---')
            ->call('addDelimiter')
            ->assertSeeHtml('data-book-break');

        $delimiter = Book::query()->where('kind', Book::KIND_BOOK)->where('is_delimiter', true)->first();

        $this->assertNotNull($delimiter);
        $this->assertSame('', $delimiter->title);
        $this->assertSame(1, $delimiter->position);
        $this->assertSame(2, $book->fresh()->position);
        $this->assertSame(Book::STATUS_READING, $delimiter->status);

        Livewire::test(Read::class)
            ->call('reorderBooks', [$delimiter->id, $book->id])
            ->call('selectBook', $delimiter->id)
            ->assertSet('selectedBookId', null)
            ->call('startEditBook', $delimiter->id)
            ->assertSet('editingBookId', null)
            ->call('markBookFinished', $delimiter->id);

        $this->assertSame(1, $delimiter->fresh()->position);
        $this->assertSame(2, $book->fresh()->position);
        $this->assertFalse($delimiter->fresh()->isFinished());
        $this->assertTrue($delimiter->fresh()->isDelimiter());
    }

    public function test_articles_can_add_a_delimiter(): void
    {
        Book::query()->create([
            'title' => 'Why We Sleep',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_ARTICLE,
        ]);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE])
            ->call('addDelimiter')
            ->assertSeeHtml('data-book-break');

        $delimiter = Book::query()->where('is_delimiter', true)->first();

        $this->assertNotNull($delimiter);
        $this->assertSame(Book::KIND_ARTICLE, $delimiter->kind);
        $this->assertSame(1, $delimiter->position);
        $this->assertSame(2, Book::query()->where('title', 'Why We Sleep')->value('position'));
        $this->assertNull(Book::query()->where('kind', Book::KIND_BOOK)->where('is_delimiter', true)->first());
    }

    public function test_deleting_a_delimiter_does_not_change_selection(): void
    {
        $book = Book::query()->create([
            'title' => 'Keep',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_BOOK,
        ]);
        $delimiter = Book::query()->create([
            'title' => '',
            'status' => Book::STATUS_READING,
            'position' => 2,
            'kind' => Book::KIND_BOOK,
            'is_delimiter' => true,
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('deleteBook', $delimiter->id)
            ->assertSet('selectedBookId', $book->id)
            ->assertDontSeeHtml('data-book-break');

        $this->assertNull(Book::query()->find($delimiter->id));
    }
}
