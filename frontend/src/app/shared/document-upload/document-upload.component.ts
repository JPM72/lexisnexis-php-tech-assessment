import { Component, EventEmitter, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatListModule } from '@angular/material/list';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { DocumentService } from '../../core/document.service';
import { forkJoin } from 'rxjs';

interface UploadResult {
  filename: string;
  success: boolean;
  message: string;
}

@Component({
  selector: 'app-document-upload',
  standalone: true,
  imports: [
    CommonModule,
    MatIconModule,
    MatButtonModule,
    MatListModule,
    MatProgressBarModule,
    MatSnackBarModule
  ],
  templateUrl: './document-upload.component.html',
  styleUrl: './document-upload.component.scss'
})
export class DocumentUploadComponent {
  @Output() uploadCompleted = new EventEmitter<void>();

  selectedFiles: File[] = [];
  isDragOver = false;
  uploading = false;
  uploadResults: UploadResult[] = [];

  constructor(
    private documentService: DocumentService,
    private snackBar: MatSnackBar
  ) {}

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver = true;
  }

  onDragLeave(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver = false;
  }

  onFileDropped(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver = false;

    const files = event.dataTransfer?.files;
    if (files) {
      this.handleFiles(files);
    }
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files) {
      this.handleFiles(input.files);
    }
  }

  private handleFiles(fileList: FileList): void {
    const files = Array.from(fileList);
    
    // Validate files
    const validFiles = files.filter(file => {
      if (!this.documentService.isValidFileType(file)) {
        this.snackBar.open(`Invalid file type: ${file.name}`, 'Close', {
          duration: 3000,
          panelClass: 'error-snackbar'
        });
        return false;
      }
      
      if (!this.documentService.isValidFileSize(file)) {
        this.snackBar.open(`File too large: ${file.name}`, 'Close', {
          duration: 3000,
          panelClass: 'error-snackbar'
        });
        return false;
      }
      
      return true;
    });

    // Add valid files to selection (avoid duplicates)
    validFiles.forEach(file => {
      const exists = this.selectedFiles.some(existing => 
        existing.name === file.name && existing.size === file.size
      );
      if (!exists) {
        this.selectedFiles.push(file);
      }
    });
  }

  removeFile(index: number): void {
    this.selectedFiles.splice(index, 1);
  }

  clearFiles(): void {
    this.selectedFiles = [];
  }

  uploadFiles(): void {
    if (this.selectedFiles.length === 0) return;

    this.uploading = true;
    this.uploadResults = [];

    // Upload files in parallel
    const uploadObservables = this.selectedFiles.map(file => 
      this.documentService.uploadDocument(file)
    );

    forkJoin(uploadObservables).subscribe({
      next: (results) => {
        this.uploading = false;
        this.uploadResults = results.map((result, index) => ({
          filename: this.selectedFiles[index].name,
          success: true,
          message: 'Upload successful'
        }));
        
        this.snackBar.open(`Successfully uploaded ${results.length} files`, 'Close', {
          duration: 3000,
          panelClass: 'success-snackbar'
        });
        
        this.selectedFiles = [];
        this.uploadCompleted.emit();
      },
      error: (error) => {
        this.uploading = false;
        this.uploadResults = this.selectedFiles.map(file => ({
          filename: file.name,
          success: false,
          message: error.message || 'Upload failed'
        }));
        
        this.snackBar.open('Upload failed', 'Close', {
          duration: 3000,
          panelClass: 'error-snackbar'
        });
      }
    });
  }

  clearResults(): void {
    this.uploadResults = [];
  }

  formatFileSize(bytes: number): string {
    return this.documentService.formatFileSize(bytes);
  }
}
