import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { RowContextMenu, RowContextMenuItem } from '../RowContextMenu.jsx';

describe('RowContextMenu focus restoration', () => {
  it('restores focus to keyboard trigger when menu closes', () => {
    const onClose = vi.fn();
    const triggerRef = { current: null };

    const { rerender } = render(
      <button ref={(element) => { triggerRef.current = element; }} type="button">
        Open menu
      </button>,
    );
    triggerRef.current.focus();

    rerender(
      <>
        <button ref={(element) => { triggerRef.current = element; }} type="button">
          Open menu
        </button>
        <RowContextMenu x={10} y={10} anchorRect={{ left: 0, right: 40, bottom: 20 }} triggerRef={triggerRef} onClose={onClose}>
          <RowContextMenuItem label="View" onClick={onClose} />
        </RowContextMenu>
      </>,
    );

    fireEvent.mouseDown(document.querySelector('.fixed.inset-0 > .absolute.inset-0'));

    expect(onClose).toHaveBeenCalled();
    expect(triggerRef.current).toHaveFocus();
  });

  it('restores focus on unmount and does not steal focus for pointer menus', () => {
    const trigger = document.createElement('button');
    document.body.append(trigger);
    trigger.focus();

    const { unmount } = render(
      <RowContextMenu x={10} y={10} anchorRect={{ left: 0, right: 40, bottom: 20 }} triggerRef={{ current: trigger }} onClose={vi.fn()}>
        <RowContextMenuItem label="View" onClick={vi.fn()} />
      </RowContextMenu>,
    );
    unmount();

    expect(trigger).toHaveFocus();
    trigger.remove();

    const pointerTarget = document.createElement('button');
    document.body.append(pointerTarget);
    pointerTarget.focus();
    const { unmount: unmountPointerMenu } = render(
      <RowContextMenu x={10} y={10} onClose={vi.fn()}>
        <RowContextMenuItem label="View" onClick={vi.fn()} />
      </RowContextMenu>,
    );

    expect(pointerTarget).toHaveFocus();
    unmountPointerMenu();
    pointerTarget.remove();
  });
});
