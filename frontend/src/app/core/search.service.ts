import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, map } from 'rxjs/operators';
import { ApiService } from './api.service';
import { SearchResult, SearchResponse } from './models';

@Injectable({
  providedIn: 'root'
})
export class SearchService {
  private searchResultsSubject = new BehaviorSubject<SearchResult[]>([]);
  public searchResults$ = this.searchResultsSubject.asObservable();

  private loadingSubject = new BehaviorSubject<boolean>(false);
  public loading$ = this.loadingSubject.asObservable();

  private currentQuerySubject = new BehaviorSubject<string>('');
  public currentQuery$ = this.currentQuerySubject.asObservable();

  private searchMetadataSubject = new BehaviorSubject<any>(null);
  public searchMetadata$ = this.searchMetadataSubject.asObservable();

  // For real-time search
  private searchTerms = new Subject<string>();

  constructor(private apiService: ApiService) {
    // Setup debounced search
    this.searchTerms.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap((term: string) => {
        if (term.trim().length < 2) {
          this.searchResultsSubject.next([]);
          this.searchMetadataSubject.next(null);
          return [];
        }
        return this.performSearch(term);
      })
    ).subscribe();
  }

  // Real-time search with debouncing
  search(term: string): void {
    this.currentQuerySubject.next(term);
    this.searchTerms.next(term);
  }

  // Direct search method
  performSearch(
    query: string, 
    page: number = 1, 
    limit: number = 10, 
    sortBy: string = 'relevance',
    sortOrder: string = 'DESC',
    mode: string = 'natural'
  ): Observable<SearchResponse> {
    if (query.trim().length < 2) {
      this.searchResultsSubject.next([]);
      this.searchMetadataSubject.next(null);
      return new Observable(observer => observer.next({
        data: [],
        pagination: {
          current_page: 1,
          per_page: limit,
          total: 0,
          total_pages: 0,
          has_next: false,
          has_prev: false,
          next_page: null,
          prev_page: null
        },
        metadata: {
          query,
          execution_time_ms: 0,
          page,
          limit,
          sort_by: sortBy,
          sort_order: sortOrder,
          search_mode: mode
        }
      }));
    }

    this.loadingSubject.next(true);
    
    return this.apiService.searchDocuments(query, page, limit, sortBy, sortOrder, mode).pipe(
      map(response => {
        this.loadingSubject.next(false);
        if (response.success) {
          this.searchResultsSubject.next(response.data.data);
          this.searchMetadataSubject.next(response.data.metadata);
          return response.data;
        }
        throw new Error(response.message);
      })
    );
  }

  clearSearch(): void {
    this.currentQuerySubject.next('');
    this.searchResultsSubject.next([]);
    this.searchMetadataSubject.next(null);
  }

  highlightSearchTerms(text: string, query: string): string {
    if (!text || !query) return text;
    
    const terms = query.toLowerCase().split(' ').filter(term => term.length > 1);
    let highlighted = text;
    
    terms.forEach(term => {
      const regex = new RegExp(`(${term})`, 'gi');
      highlighted = highlighted.replace(regex, '<mark>$1</mark>');
    });
    
    return highlighted;
  }
}
