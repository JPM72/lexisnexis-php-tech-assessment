import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ApiService } from './api.service';
import { Document, DocumentListResponse, UploadResponse } from './models';

@Injectable({
  providedIn: 'root'
})
export class DocumentService {
  private documentsSubject = new BehaviorSubject<Document[]>([]);
  public documents$ = this.documentsSubject.asObservable();

  private loadingSubject = new BehaviorSubject<boolean>(false);
  public loading$ = this.loadingSubject.asObservable();

  constructor(private apiService: ApiService) {}

  loadDocuments(page: number = 1, limit: number = 10): Observable<DocumentListResponse> {
    this.loadingSubject.next(true);
    
    return this.apiService.getDocuments(page, limit).pipe(
      map(response => {
        this.loadingSubject.next(false);
        if (response.success) {
          this.documentsSubject.next(response.data.data);
          return response.data;
        }
        throw new Error(response.message);
      })
    );
  }

  uploadDocument(file: File, title?: string): Observable<UploadResponse> {
    this.loadingSubject.next(true);
    
    return this.apiService.uploadDocument(file, title).pipe(
      map(response => {
        this.loadingSubject.next(false);
        if (response.success) {
          return response.data;
        }
        throw new Error(response.message);
      })
    );
  }

  deleteDocument(id: number): Observable<any> {
    this.loadingSubject.next(true);
    
    return this.apiService.deleteDocument(id).pipe(
      map(response => {
        this.loadingSubject.next(false);
        if (response.success) {
          return response.data;
        }
        throw new Error(response.message);
      })
    );
  }

  getDocument(id: number): Observable<any> {
    return this.apiService.getDocument(id).pipe(
      map(response => {
        if (response.success) {
          return response.data;
        }
        throw new Error(response.message);
      })
    );
  }

  formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
  }

  isValidFileType(file: File): boolean {
    const allowedTypes = ['text/plain', 'application/pdf'];
    return allowedTypes.includes(file.type);
  }

  isValidFileSize(file: File, maxSizeMB: number = 10): boolean {
    return file.size <= maxSizeMB * 1024 * 1024;
  }
}
