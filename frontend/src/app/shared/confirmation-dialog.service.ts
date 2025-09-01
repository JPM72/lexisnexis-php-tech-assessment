import { Injectable } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { Observable } from 'rxjs';
import { ConfirmationDialogComponent, ConfirmationDialogData } from './confirmation-dialog/confirmation-dialog.component';

@Injectable({
  providedIn: 'root'
})
export class ConfirmationDialogService {

  constructor(private dialog: MatDialog) { }

  confirm(data: ConfirmationDialogData): Observable<boolean> {
    const dialogRef = this.dialog.open(ConfirmationDialogComponent, {
      width: '400px',
      disableClose: true,
      data
    });

    return dialogRef.afterClosed();
  }

  // Convenience methods for common confirmation dialogs
  confirmDelete(itemName: string = 'item'): Observable<boolean> {
    return this.confirm({
      title: 'Confirm Deletion',
      message: `Are you sure you want to delete this ${itemName}? This action cannot be undone.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
      icon: 'delete',
      color: 'warn'
    });
  }

  confirmAction(title: string, message: string, actionText: string = 'Confirm'): Observable<boolean> {
    return this.confirm({
      title,
      message,
      confirmText: actionText,
      cancelText: 'Cancel',
      icon: 'help',
      color: 'primary'
    });
  }
}
