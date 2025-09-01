import { Injectable } from '@angular/core';
import { HttpClient, HttpParams, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ApiResponse, DocumentListResponse, SearchResponse, UploadResponse } from './models';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private readonly baseUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  // Document endpoints
  getDocuments(page: number = 1, limit: number = 10): Observable<ApiResponse<DocumentListResponse>> {
    const params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString());
    
    return this.http.get<ApiResponse<DocumentListResponse>>(`${this.baseUrl}/documents`, { params });
  }

  getDocument(id: number): Observable<ApiResponse<any>> {
    return this.http.get<ApiResponse<any>>(`${this.baseUrl}/documents/${id}`);
  }

  uploadDocument(file: File, title?: string): Observable<ApiResponse<UploadResponse>> {
    const formData = new FormData();
    formData.append('document', file);
    if (title) {
      formData.append('title', title);
    }

    return this.http.post<ApiResponse<UploadResponse>>(`${this.baseUrl}/documents`, formData);
  }

  deleteDocument(id: number): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${this.baseUrl}/documents/${id}`);
  }

  // Search endpoints
  searchDocuments(
    query: string,
    page: number = 1,
    limit: number = 10,
    sortBy: string = 'relevance',
    sortOrder: string = 'DESC',
    mode: string = 'natural'
  ): Observable<ApiResponse<SearchResponse>> {
    const params = new HttpParams()
      .set('q', query)
      .set('page', page.toString())
      .set('limit', limit.toString())
      .set('sort', sortBy)
      .set('order', sortOrder)
      .set('mode', mode);

    return this.http.get<ApiResponse<SearchResponse>>(`${this.baseUrl}/search`, { params });
  }

  // Health check
  healthCheck(): Observable<any> {
    return this.http.get(`${this.baseUrl}/health`);
  }
}
