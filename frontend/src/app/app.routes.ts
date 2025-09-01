import { Routes } from '@angular/router';
import { DocumentsComponent } from './features/documents/documents.component';
import { SearchComponent } from './features/search/search.component';

export const routes: Routes = [
  { path: '', redirectTo: '/documents', pathMatch: 'full' },
  { path: 'documents', component: DocumentsComponent },
  { path: 'search', component: SearchComponent },
  { path: '**', redirectTo: '/documents' }
];
