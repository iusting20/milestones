<?php

namespace Tests\Feature;

use App\Livewire\Read;
use App\Models\Book;
use App\Models\BookFeedback;
use App\Models\BookFeedbackEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $attrs = []): Book
    {
        return Book::query()->create(array_merge([
            'title' => 'Finished Book',
            'status' => Book::STATUS_FINISHED,
            'position' => 1,
            'kind' => Book::KIND_BOOK,
        ], $attrs));
    }

    public function test_feedback_button_appears_for_finished_items_and_unfinished_books(): void
    {
        $openBook = $this->book([
            'title' => 'Open Book',
            'status' => Book::STATUS_READING,
        ]);
        $finishedBook = $this->book([
            'title' => 'Finished Book',
            'position' => 2,
        ]);
        $openArticle = $this->book([
            'title' => 'Open Article',
            'status' => Book::STATUS_READING,
            'kind' => Book::KIND_ARTICLE,
        ]);
        $finishedArticle = $this->book([
            'title' => 'Finished Article',
            'kind' => Book::KIND_ARTICLE,
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $openBook->id])
            ->assertSee("Mark as 'finished'", false)
            ->assertSee('Give feedback')
            ->assertDontSee('View feedback')
            ->assertSeeHtml('read-feedback-btn-outline')
            ->assertSeeHtml('col-span-2 sm:col-span-4')
            ->assertDontSeeHtml('hover:brightness-105');

        Livewire::test(Read::class, ['selectedBookId' => $finishedBook->id])
            ->assertSee('Give feedback')
            ->assertDontSee('View feedback')
            ->assertSeeHtml('hover:brightness-105')
            ->assertSeeHtml('col-span-2 sm:col-span-4')
            ->assertDontSeeHtml('read-feedback-btn-outline')
            ->assertDontSee("Mark as 'finished'", false)
            ->assertSee('Mark as unfinished');

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $openArticle->id])
            ->assertDontSee('Give feedback')
            ->assertDontSeeHtml('read-feedback-btn-outline');

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $finishedArticle->id])
            ->assertSee('Give feedback')
            ->assertSeeHtml('hover:brightness-105')
            ->assertDontSeeHtml('read-feedback-btn-outline');
    }

    public function test_saving_description_likes_and_dislikes_switches_the_label(): void
    {
        $book = $this->book(['title' => 'Atomic Habits']);

        $component = Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSee('Give feedback')
            ->call('openFeedback', $book->id)
            ->assertSet('feedbackOpen', true)
            ->assertSet('feedbackIdeasOnly', false)
            ->assertSee('Feedback · Atomic Habits', false)
            ->assertSee('Ideas')
            ->assertSee('Likes')
            ->assertSee('Dislikes')
            ->assertSeeHtml('x-on:book-feedback-saved.window')
            ->assertSeeHtml('modal-saved-glow')
            ->assertSeeHtml('autogrow-textarea')
            ->assertDontSeeHtml('x-init="$nextTick(() => $el.focus())"')
            ->assertDontSeeHtml('autofocus')
            ->set('feedbackDescription', 'A clear, practical guide.')
            ->call('addFeedbackEntry', 'like')
            ->set('feedbackLikes.0.body', 'Short chapters')
            ->call('addFeedbackEntry', 'dislike')
            ->set('feedbackDislikes.0.body', 'A bit repetitive')
            ->call('saveFeedback')
            ->assertDispatched('book-feedback-saved')
            ->assertSet('feedbackDirty', false)
            ->assertSee('View feedback')
            ->assertDontSee('Give feedback');

        $this->assertDatabaseHas('book_feedback', [
            'book_id' => $book->id,
            'description' => 'A clear, practical guide.',
        ]);
        $this->assertDatabaseHas('book_feedback_entries', [
            'book_id' => $book->id,
            'kind' => BookFeedbackEntry::KIND_LIKE,
            'body' => 'Short chapters',
        ]);
        $this->assertDatabaseHas('book_feedback_entries', [
            'book_id' => $book->id,
            'kind' => BookFeedbackEntry::KIND_DISLIKE,
            'body' => 'A bit repetitive',
        ]);

        $component->call('addFeedbackEntry', 'like')
            ->set('feedbackLikes.1.body', '   ')
            ->call('removeFeedbackEntry', 'like', 1)
            ->assertCount('feedbackLikes', 1);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $this->book([
            'title' => 'Finished Article',
            'kind' => Book::KIND_ARTICLE,
        ])->id])
            ->call('openFeedback', Book::query()->where('title', 'Finished Article')->value('id'))
            ->set('feedbackDescription', 'Useful overview.')
            ->call('saveFeedback')
            ->assertDispatched('book-feedback-saved')
            ->assertSee('View feedback');
    }

    public function test_unfinished_books_open_ideas_by_chapter_only(): void
    {
        $book = $this->book([
            'title' => 'Open Book',
            'status' => Book::STATUS_READING,
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSee('Give feedback')
            ->call('openFeedback', $book->id)
            ->assertSet('feedbackOpen', true)
            ->assertSet('feedbackIdeasOnly', true)
            ->assertSee('Ideas by chapter')
            ->assertDontSee('Feedback · Open Book', false)
            ->assertDontSee('← Back to Ideas', false)
            ->assertDontSee('View ideas by chapter')
            ->assertDontSee('Likes')
            ->assertDontSee('Dislikes')
            ->assertDontSeeHtml('role="tablist"')
            ->call('addFeedbackEntry', 'like')
            ->assertSet('feedbackLikes', [])
            ->set('feedbackDescription', 'Should not persist')
            ->set('feedbackLikes', [['id' => null, 'body' => 'Should not persist']])
            ->set('feedbackDislikes', [['id' => null, 'body' => 'Should not persist']])
            ->call('addFeedbackIdea', 'general')
            ->set('feedbackChapters.0.ideas.0.body', 'A note mid-read')
            ->call('saveFeedback')
            ->assertDispatched('book-feedback-saved')
            ->assertSet('feedbackDirty', false)
            ->assertSee('View feedback')
            ->assertDontSee('Give feedback');

        $this->assertDatabaseHas('book_feedback_ideas', [
            'book_id' => $book->id,
            'section_id' => null,
            'body' => 'A note mid-read',
        ]);
        $this->assertDatabaseCount('book_feedback', 0);
        $this->assertDatabaseCount('book_feedback_entries', 0);
        $this->assertTrue($book->fresh()->hasChapterIdeas());
        $this->assertTrue($book->fresh()->hasFeedback());
    }

    public function test_ideas_only_save_does_not_alter_existing_description_or_entries(): void
    {
        $book = $this->book([
            'title' => 'Was Finished',
            'status' => Book::STATUS_READING,
        ]);
        BookFeedback::query()->create([
            'book_id' => $book->id,
            'description' => 'Keep me',
        ]);
        BookFeedbackEntry::query()->create([
            'book_id' => $book->id,
            'kind' => BookFeedbackEntry::KIND_LIKE,
            'body' => 'Keep like',
            'position' => 1,
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSee('Give feedback')
            ->assertDontSee('View feedback')
            ->call('openFeedback', $book->id)
            ->assertSet('feedbackIdeasOnly', true)
            ->assertSet('feedbackDescription', 'Keep me')
            ->call('addFeedbackIdea', 'general')
            ->set('feedbackChapters.0.ideas.0.body', 'New idea')
            ->set('feedbackDescription', 'Overwrite attempt')
            ->set('feedbackLikes.0.body', 'Overwrite like')
            ->call('saveFeedback')
            ->assertDispatched('book-feedback-saved');

        $this->assertDatabaseHas('book_feedback', [
            'book_id' => $book->id,
            'description' => 'Keep me',
        ]);
        $this->assertDatabaseHas('book_feedback_entries', [
            'book_id' => $book->id,
            'kind' => BookFeedbackEntry::KIND_LIKE,
            'body' => 'Keep like',
        ]);
        $this->assertDatabaseHas('book_feedback_ideas', [
            'book_id' => $book->id,
            'body' => 'New idea',
        ]);
        $this->assertDatabaseCount('book_feedback_entries', 1);
    }

    public function test_unfinished_articles_cannot_open_the_feedback_modal(): void
    {
        $article = $this->book([
            'title' => 'Open Article',
            'status' => Book::STATUS_READING,
            'kind' => Book::KIND_ARTICLE,
        ]);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $article->id])
            ->assertDontSee('Give feedback')
            ->call('openFeedback', $article->id)
            ->assertSet('feedbackOpen', false);

        $this->assertDatabaseCount('book_feedback', 0);
        $this->assertDatabaseCount('book_feedback_ideas', 0);
    }

    public function test_empty_entries_are_not_stored_and_do_not_count_as_feedback(): void
    {
        $book = $this->book();

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('openFeedback', $book->id)
            ->call('addFeedbackEntry', 'like')
            ->set('feedbackLikes.0.body', '   ')
            ->call('saveFeedback')
            ->assertDispatched('book-feedback-saved')
            ->assertSee('Give feedback')
            ->assertDontSee('View feedback');

        $this->assertDatabaseCount('book_feedback', 0);
        $this->assertDatabaseCount('book_feedback_entries', 0);
    }
}
