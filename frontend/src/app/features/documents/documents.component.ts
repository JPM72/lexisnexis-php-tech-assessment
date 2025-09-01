import { Component, OnInit, ViewChild, AfterViewInit } from '@angular/core'
import { CommonModule } from '@angular/common'
import { MatCardModule } from '@angular/material/card'
import { MatButtonModule } from '@angular/material/button'
import { MatIconModule } from '@angular/material/icon'
import { MatTableModule } from '@angular/material/table'
import { MatPaginatorModule, MatPaginator, PageEvent } from '@angular/material/paginator'
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner'
import { MatTableDataSource } from '@angular/material/table'
import { DocumentUploadComponent } from '../../shared/document-upload/document-upload.component'
import { DocumentService } from '../../core/document.service'
import { Document, PaginationInfo } from '../../core/models'
import { Observable } from 'rxjs'
import { ConfirmationDialogService } from '../../shared/confirmation-dialog.service'

@Component({
	selector: 'app-documents',
	standalone: true,
	imports: [
		CommonModule,
		MatCardModule,
		MatButtonModule,
		MatIconModule,
		MatTableModule,
		MatPaginatorModule,
		MatProgressSpinnerModule,
		DocumentUploadComponent
	],
	templateUrl: './documents.component.html',
	styleUrl: './documents.component.scss'
})
export class DocumentsComponent implements OnInit, AfterViewInit
{
	documents$: Observable<Document[]>
	loading$: Observable<boolean>
	displayedColumns: string[] = ['title', 'filename', 'size', 'type', 'created', 'actions'];
	documents: Document[] = [];
	dataSource = new MatTableDataSource<Document>();
	paginationInfo: PaginationInfo | null = null;
	currentPage = 1;
	pageSize = 10;

	@ViewChild(MatPaginator) paginator!: MatPaginator;

	constructor(
		private documentService: DocumentService,
		private confirmationService: ConfirmationDialogService
	)
	{
		this.documents$ = this.documentService.documents$
		this.loading$ = this.documentService.loading$

		// Subscribe to documents to handle the data source properly
		this.documents$.subscribe(docs => this.documents = docs)
	}

	ngOnInit(): void
	{
		this.loadDocuments()
	}

	ngAfterViewInit() {
		// Set up paginator after view initialization
		if (this.paginator) {
			this.dataSource.paginator = this.paginator;
		}
	}

	loadDocuments(page: number = 1, limit: number = 10): void
	{
		this.currentPage = page;
		this.pageSize = limit;
		this.documentService.loadDocuments(page, limit).subscribe(
			response => {
				this.documents = response.data;
				this.dataSource.data = response.data;
				this.paginationInfo = response.pagination;
				
				// Update paginator with server-side pagination info
				if (this.paginator) {
					this.paginator.length = response.pagination.total;
					this.paginator.pageIndex = response.pagination.current_page - 1;
					this.paginator.pageSize = response.pagination.per_page;
				}
			},
			error => {
				console.error('Error loading documents:', error);
			}
		);
	}

	onUploadCompleted(): void
	{
		this.loadDocuments(this.currentPage, this.pageSize)
	}

	deleteDocument(id: number): void
	{
		this.confirmationService.confirmDelete('document').subscribe(confirmed => {
			if (confirmed) {
				this.documentService.deleteDocument(id).subscribe(
					() => {
						// After deletion, reload current page
						this.loadDocuments(this.currentPage, this.pageSize)
					},
					error => {
						console.error('Error deleting document:', error);
					}
				);
			}
		})
	}

	formatFileSize(bytes: number): string
	{
		return this.documentService.formatFileSize(bytes)
	}

	onPageChange(event: PageEvent): void
	{
		const page = event.pageIndex + 1; // Angular paginator is 0-based, our API is 1-based
		const limit = event.pageSize;
		this.loadDocuments(page, limit);
	}
}
