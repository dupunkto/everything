interface Options {
  listClasses: string[];
  inputClass?: string;
}

export function keyboardNavigation(node: HTMLElement, options: Options) {
  let { listClasses, inputClass } = options;

  let currentListIndex = 0;
  let currentItemIndex = -1;
  
  const getItems = (listClass: string): HTMLElement[] => {
    const items = node.querySelectorAll(`.${listClass} .item`);
    return Array.from(items).filter((el: Element) => {
      const rect = el.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    }) as HTMLElement[];
  };
  
  const getAllItems = (): HTMLElement[][] => {
    return listClasses.map(getItems);
  };
  
  const focus = (element: HTMLElement | null) => {
    if (!element) return;
    element.focus();
    element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };
  
  const focusInput = () => {
    if (!inputClass) return;
    const input = node.querySelector(`.${inputClass}`) as HTMLElement;
    focus(input);
    currentListIndex = -1;
    currentItemIndex = -1;
  };
  
  const updateFocus = () => {
    const allItems = getAllItems();
    
    if (currentListIndex >= 0 && currentListIndex < allItems.length) {
      const items = allItems[currentListIndex];
      if (currentItemIndex >= 0 && currentItemIndex < items.length) {
        focus(items[currentItemIndex]);
        return;
      }
    }
    
    if (inputClass) focusInput();
  };
  
  const navigateUp = () => {
    const allItems = getAllItems();
    if (allItems.length === 0) return;

    if (currentListIndex === -1 && inputClass) return;
    
    if (currentListIndex >= 0 && allItems[currentListIndex]) {
      const items = allItems[currentListIndex];
      
      if (currentItemIndex > 0) {
        currentItemIndex--;
      } else if (currentItemIndex === 0) {
        if (inputClass && currentListIndex === 0) {
          focusInput();
          return;
        }
        
        let prevListIndex = currentListIndex - 1;
        while (prevListIndex >= 0 && allItems[prevListIndex]?.length == 0)
          prevListIndex--;
        
        if (prevListIndex >= 0) {
          currentListIndex = prevListIndex;
          currentItemIndex = allItems[currentListIndex].length - 1;
        } else if (inputClass) {
          focusInput();
          return;
        }
      }
    }
    
    updateFocus();
  };
  
  const navigateDown = () => {
    const allItems = getAllItems();
    if (allItems.length === 0) return;
    
    if (currentListIndex === -1 && inputClass) {
      currentListIndex = 0;
      currentItemIndex = 0;
      
      while (currentListIndex < allItems.length && allItems[currentListIndex]?.length === 0) {
        currentListIndex++;
      }
      
      if (currentListIndex >= allItems.length) {
        currentListIndex = -1;
        return;
      }
    }
    else if (currentListIndex >= 0 && allItems[currentListIndex]) {
      const items = allItems[currentListIndex];
      
      if (currentItemIndex < items.length - 1) {
        currentItemIndex++;
      } else {
        let nextListIndex = currentListIndex + 1;
        while (nextListIndex < allItems.length && allItems[nextListIndex]?.length === 0) {
          nextListIndex++;
        }
        
        if (nextListIndex < allItems.length) {
          currentListIndex = nextListIndex;
          currentItemIndex = 0;
        }
      }
    }
    
    updateFocus();
  };
  
  const handleKeydown = (e: KeyboardEvent) => {
    const isNonSearchInput = (element: Element | null) => {
      if ((element as HTMLElement)?.isContentEditable) return true;
      if (element?.tagName === 'TEXTAREA') return true;
      if (element?.tagName === 'INPUT' && !element.classList.contains(inputClass || '')) return true;
      return false;
    };
    
    if (e.key == 'ArrowUp') {
      if (isNonSearchInput(document.activeElement)) return;
      e.preventDefault(); navigateUp();
    }
    
    if (e.key == 'ArrowDown') {
      if (isNonSearchInput(document.activeElement)) return;
      e.preventDefault(); navigateDown();
    }
  };
  
  document.addEventListener('keydown', handleKeydown);
  
  return {
    update(options: Options) {
      listClasses = options.listClasses;
      inputClass = options.inputClass;
    },
    destroy() {
      document.removeEventListener('keydown', handleKeydown);
    }
  };
}