import { Component, Inject } from '@angular/core'
import { CommonModule } from '@angular/common'
import { MatDialogModule, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog'
import { MatButtonModule } from '@angular/material/button'
import { MatIconModule } from '@angular/material/icon'

export interface ConfirmationDialogData
{
	title: string
	message: string
	confirmText?: string
	cancelText?: string
	icon?: string
	color?: 'primary' | 'accent' | 'warn'
}

@Component({
	selector: 'app-confirmation-dialog',
	standalone: true,
	imports: [
		CommonModule,
		MatDialogModule,
		MatButtonModule,
		MatIconModule
	],
	templateUrl: './confirmation-dialog.component.html',
	styleUrl: './confirmation-dialog.component.scss'
})
export class ConfirmationDialogComponent
{
	constructor(
		public dialogRef: MatDialogRef<ConfirmationDialogComponent>,
		@Inject(MAT_DIALOG_DATA) public data: ConfirmationDialogData
	)
	{
		// Set defaults
		this.data = {
			confirmText: 'Confirm',
			cancelText: 'Cancel',
			icon: 'help',
			color: 'primary',
			...data
		}
	}

	onConfirm(): void
	{
		this.dialogRef.close(true)
	}

	onCancel(): void
	{
		this.dialogRef.close(false)
	}
}
