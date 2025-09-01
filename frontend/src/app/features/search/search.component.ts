import { Component, OnInit, OnDestroy } from '@angular/core'
import { CommonModule } from '@angular/common'
import { FormsModule } from '@angular/forms'
import { MatCardModule } from '@angular/material/card'
import { MatFormFieldModule } from '@angular/material/form-field'
import { MatInputModule } from '@angular/material/input'
import { MatButtonModule } from '@angular/material/button'
import { MatIconModule } from '@angular/material/icon'
import { MatSelectModule } from '@angular/material/select'
import { MatChipsModule } from '@angular/material/chips'
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner'
import { MatDividerModule } from '@angular/material/divider'
import { MatListModule } from '@angular/material/list'
import { MatBadgeModule } from '@angular/material/badge'
import { MatTooltipModule } from '@angular/material/tooltip'
import { SearchService } from '../../core/search.service'
import { SearchResult } from '../../core/models'
import { Observable, Subscription } from 'rxjs'

@Component({
	selector: 'app-search',
	standalone: true,
	imports: [
		CommonModule,
		FormsModule,
		MatCardModule,
		MatFormFieldModule,
		MatInputModule,
		MatButtonModule,
		MatIconModule,
		MatSelectModule,
		MatChipsModule,
		MatProgressSpinnerModule,
		MatDividerModule,
		MatListModule,
		MatBadgeModule,
		MatTooltipModule
	],
	templateUrl: './search.component.html',
	styleUrl: './search.component.scss'
})
export class SearchComponent implements OnInit, OnDestroy
{
	searchQuery: string = '';
	searchResults$: Observable<SearchResult[]>
	loading$: Observable<boolean>
	searchMetadata$: Observable<any>
	currentQuery$: Observable<string>

	// Search options
	sortBy: string = 'relevance';
	sortOrder: string = 'DESC';
	searchMode: string = 'natural';
	resultsPerPage: number = 10;
	currentPage: number = 1;

	// Available options
	sortOptions = [
		{ value: 'relevance', label: 'Relevance' },
		{ value: 'created_at', label: 'Date Created' },
		{ value: 'title', label: 'Title' },
		{ value: 'file_size', label: 'File Size' }
	];

	sortOrderOptions = [
		{ value: 'DESC', label: 'Descending' },
		{ value: 'ASC', label: 'Ascending' }
	];

	searchModeOptions = [
		{ value: 'natural', label: 'Natural Language' },
		{ value: 'boolean', label: 'Boolean Search' },
		{ value: 'wildcard', label: 'Wildcard Search' }
	];

	resultsPerPageOptions = [5, 10, 20, 50];

	private subscriptions: Subscription = new Subscription();

	constructor(private searchService: SearchService)
	{
		this.searchResults$ = this.searchService.searchResults$
		this.loading$ = this.searchService.loading$
		this.searchMetadata$ = this.searchService.searchMetadata$
		this.currentQuery$ = this.searchService.currentQuery$
	}

	ngOnInit(): void
	{
		// Subscribe to current query to keep input in sync
		this.subscriptions.add(
			this.currentQuery$.subscribe(query =>
			{
				if (query !== this.searchQuery)
				{
					this.searchQuery = query
				}
			})
		)
	}

	ngOnDestroy(): void
	{
		this.subscriptions.unsubscribe()
	}

	onSearchInput(): void
	{
		if (this.searchQuery.trim().length >= 2)
		{
			this.searchService.search(this.searchQuery.trim())
		} else
		{
			this.searchService.clearSearch()
		}
	}

	performAdvancedSearch(): void
	{
		if (this.searchQuery.trim().length < 2)
		{
			return
		}

		this.searchService.performSearch(
			this.searchQuery.trim(),
			this.currentPage,
			this.resultsPerPage,
			this.sortBy,
			this.sortOrder,
			this.searchMode
		).subscribe()
	}

	clearSearch(): void
	{
		this.searchQuery = ''
		this.searchService.clearSearch()
		this.resetSearchOptions()
	}

	private resetSearchOptions(): void
	{
		this.sortBy = 'relevance'
		this.sortOrder = 'DESC'
		this.searchMode = 'natural'
		this.resultsPerPage = 10
		this.currentPage = 1
	}

	onSortChange(): void
	{
		if (this.searchQuery.trim().length >= 2)
		{
			this.performAdvancedSearch()
		}
	}

	formatFileSize(bytes: number): string
	{
		if (bytes === 0) return '0 B'
		const sizes = ['B', 'KB', 'MB', 'GB']
		const i = Math.floor(Math.log(bytes) / Math.log(1024))
		return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i]
	}

	formatExecutionTime(ms: number): string
	{
		if (ms < 1000)
		{
			return `${ms.toFixed(2)} ms`
		} else
		{
			return `${(ms / 1000).toFixed(2)} s`
		}
	}

	getRelevanceStars(score: number): number[]
	{
		const maxScore = 5
		const normalizedScore = Math.min(Math.ceil(score * maxScore), maxScore)
		return Array(normalizedScore).fill(0)
	}

	getSearchModeTooltip(): string
	{
		switch (this.searchMode)
		{
			case 'natural':
				return 'Search using natural language queries (default)'
			case 'boolean':
				return 'Use operators like +required -excluded "exact phrase"'
			case 'wildcard':
				return 'Use * for wildcard matching (e.g., docum* matches document)'
			default:
				return ''
		}
	}

	highlightText(text: string, query: string): string
	{
		if (!text || !query) return text
		return this.searchService.highlightSearchTerms(text, query)
	}

	trackByDocumentId(index: number, result: SearchResult): number
	{
		return result.id
	}
}
