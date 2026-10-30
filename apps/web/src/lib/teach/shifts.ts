/** Where a proposal stands, as the steps the teacher sees. */
export type ShiftStage = 'draft' | 'review' | 'changes' | 'countered' | 'approved' | 'rejected';

export function stageOf(status: string): ShiftStage {
  switch (status) {
    case 'draft':
      return 'draft';
    case 'submitted':
      return 'review';
    case 'changes_requested':
      return 'changes';
    case 'countered':
      return 'countered';
    case 'rejected':
      return 'rejected';
    default:
      return 'approved';
  }
}
