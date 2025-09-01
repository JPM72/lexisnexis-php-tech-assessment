import { Component, OnInit } from '@angular/core'
import { CommonModule } from '@angular/common'
import { MatCardModule } from '@angular/material/card'
import { MatButtonModule } from '@angular/material/button'
import { MatIconModule } from '@angular/material/icon'
import { MatTableModule } from '@angular/material/table'
import { MatPaginatorModule } from '@angular/material/paginator'
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner'
import { DocumentUploadComponent } from '../../shared/document-upload/document-upload.component'
import { DocumentService } from '../../core/document.service'
import { Document } from '../../core/models'
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
export class DocumentsComponent implements OnInit
{
	documents$: Observable<Document[]>
	loading$: Observable<boolean>
	displayedColumns: string[] = ['title', 'filename', 'size', 'type', 'created', 'actions'];
	documents: Document[] = [];

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

	loadDocuments(): void
	{
		this.documentService.loadDocuments().subscribe()
	}

	onUploadCompleted(): void
	{
		this.loadDocuments()
	}

	deleteDocument(id: number): void
	{
		this.confirmationService.confirmDelete('document').subscribe(confirmed => {
			if (confirmed) {
				this.documentService.deleteDocument(id).subscribe()
			}
		})
	}

	formatFileSize(bytes: number): string
	{
		return this.documentService.formatFileSize(bytes)
	}
}
