export function u(strings: TemplateStringsArray, ...values: any[]): string {
  const path = strings.reduce((result, str, i) => result + str + (values[i] ?? ''), '');
  return `javascript:history.pushState({}, "", '${path}')`;
}

export function navigate(path: string) {
  history.pushState({}, "", path);
}
